<?php

namespace App\Services;

use App\ERP\Core\Models\Company;
use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\ProcurementImportStaging;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use App\Models\TeamRole;
use App\Models\ProjectType;
use App\Models\TeamDistribution;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyProjectImportService
{
    public function __construct(
        private readonly LegacyProjectSalesQcService $qcService,
    ) {}

    /**
     * @param  list<string>  $importKeys
     * @return array<string, mixed>
     */
    public function importSelected(array $importKeys, int $performedByUserId): array
    {
        $importKeys = array_values(array_unique(array_filter(array_map('strval', $importKeys))));

        if ($importKeys === []) {
            throw new RuntimeException('Tidak ada project yang dipilih untuk diimport.');
        }

        $report = $this->qcService->buildReport();
        $projectMap = collect($report['projects'] ?? [])->keyBy('import_key');

        $selectedProjects = collect($importKeys)
            ->map(fn (string $importKey) => $projectMap->get($importKey))
            ->filter()
            ->values();

        if ($selectedProjects->isEmpty()) {
            throw new RuntimeException('Project yang dipilih tidak ditemukan di hasil QC terbaru.');
        }

        $company = Company::query()->where('name', 'OC Networks')->first();
        if (! $company) {
            throw new RuntimeException('Company OC Networks tidak ditemukan di ERP.');
        }

        $warehouse = Warehouse::query()
            ->where('company_id', $company->id)
            ->where('code', 'WH-OCN')
            ->first();

        if (! $warehouse) {
            throw new RuntimeException('Warehouse WH-OCN untuk OC Networks tidak ditemukan di ERP.');
        }

        $legacyProjectIds = $selectedProjects->pluck('legacy_id')->all();
        $legacyPaymentsByProject = $this->legacyPaymentsByProject($legacyProjectIds);

        $createdProjects = [];
        $createdStagings = [];
        $skipped = [];

        foreach ($selectedProjects as $projectData) {
            $importKey = (string) $projectData['import_key'];

            if (($projectData['readiness'] ?? '') === 'blocked') {
                $skipped[] = $this->skipMessage($projectData, 'Masih berstatus blocked di QC.');
                continue;
            }

            if (! empty($projectData['existing_erp_project'])) {
                $skipped[] = $this->skipMessage($projectData, 'Project dengan import_key yang sama sudah ada di ERP.');
                continue;
            }

            DB::transaction(function () use (
                $projectData,
                $company,
                $warehouse,
                $performedByUserId,
                $legacyPaymentsByProject,
                &$createdProjects,
                &$createdStagings
            ): void {
                $crmCustomer = ! empty($projectData['crm_customer_match']['id'])
                    ? CrmCustomer::query()->find((int) $projectData['crm_customer_match']['id'])
                    : null;

                $project = Project::query()->create([
                    'name' => (string) $projectData['title'],
                    'client_name' => (string) $projectData['customer_name'],
                    'client_contact' => $this->resolvedClientContact($crmCustomer),
                    'crm_customer_id' => $crmCustomer?->id,
                    'project_type' => $this->resolvedProjectType((string) $projectData['project_number'], (string) $projectData['title']),
                    'total_value' => number_format((float) $projectData['expected_value'], 2, '.', ''),
                    'status' => $this->resolvedProjectStatus((string) $projectData['status']),
                    'started_at' => $projectData['sale_date'] ?: null,
                    'finished_at' => $this->resolvedFinishedAt($projectData),
                    'description' => $this->legacyDescription($projectData),
                    'import_key' => (string) $projectData['import_key'],
                ]);

                $legacyPaymentRows = $legacyPaymentsByProject->get((string) $projectData['legacy_id'], collect());
                $this->importProjectPayments($project, $legacyPaymentRows, (float) $projectData['expected_value']);
                $this->importTeamAndTechnicianPayments($project, $projectData, $company->id);
                $stagingCreated = $this->importProjectMaterialsAndStaging($project, $projectData, $warehouse->id, $company->id, $performedByUserId);

                $createdProjects[] = $project->name.' ('.$project->import_key.')';
                if ($stagingCreated) {
                    $createdStagings[] = $project->import_key;
                }
            });
        }

        return [
            'created_project_count' => count($createdProjects),
            'created_staging_count' => count($createdStagings),
            'skipped' => $skipped,
            'created_projects' => $createdProjects,
        ];
    }

    /**
     * @param  list<string>  $legacyProjectIds
     * @return Collection<string, Collection<int, object>>
     */
    private function legacyPaymentsByProject(array $legacyProjectIds): Collection
    {
        if ($legacyProjectIds === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($legacyProjectIds), '?'));

        $rows = collect(DB::connection('legacy_ocn')->select(sprintf(<<<'SQL'
            SELECT
                id,
                "projectId" AS project_id,
                amount,
                method,
                notes,
                status,
                type,
                "paymentDate" AS payment_date,
                "paidDate" AS paid_date,
                "paymentNumber" AS payment_number
            FROM "Payment"
            WHERE "projectId" IN (%s)
            ORDER BY "projectId", "paymentDate", "paymentNumber"
        SQL, $placeholders), $legacyProjectIds));

        return $rows->groupBy(fn (object $row) => (string) $row->project_id);
    }

    private function importProjectPayments(Project $project, Collection $legacyPayments, float $expectedValue): void
    {
        if ($legacyPayments->isEmpty()) {
            ProjectPayment::query()->create([
                'project_id' => $project->id,
                'term_number' => 1,
                'percentage' => 100,
                'amount' => number_format($expectedValue, 2, '.', ''),
                'paid_at' => null,
                'note' => 'Imported from legacy without payment record.',
            ]);

            return;
        }

        $denominator = max($expectedValue, (float) $legacyPayments->sum(fn (object $row) => (float) $row->amount), 1);

        foreach ($legacyPayments->values() as $index => $payment) {
            $amount = (float) $payment->amount;

            ProjectPayment::query()->create([
                'project_id' => $project->id,
                'term_number' => $index + 1,
                'percentage' => number_format(($amount / $denominator) * 100, 2, '.', ''),
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => $payment->payment_date ?: $payment->paid_date,
                'note' => trim(implode(' | ', array_filter([
                    'Legacy payment '.($payment->payment_number ?? ''),
                    $payment->method ? 'Method: '.$payment->method : null,
                    $payment->type ? 'Type: '.$payment->type : null,
                    $payment->notes ?: null,
                ]))) ?: null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function importTeamAndTechnicianPayments(Project $project, array $projectData, int $companyId): void
    {
        $technicianAssignments = collect($projectData['details']['technicians'] ?? []);
        $technicianPayments = collect($projectData['details']['technician_payments'] ?? []);

        foreach ($technicianAssignments as $assignment) {
            $user = $this->resolveOrCreateTechnicianUser($assignment, $companyId);
            $relatedPayments = $technicianPayments->filter(fn (array $payment) => $this->sameTechnician($assignment, $payment));
            $paidTotal = (float) $relatedPayments->sum(fn (array $payment) => (float) ($payment['amount'] ?? 0));
            $assignmentFee = (float) ($assignment['fee'] ?? 0);
            $totalPay = max($assignmentFee, $paidTotal);
            $bonus = max($paidTotal - $assignmentFee, 0);
            $percentage = (float) ($project->total_value > 0 ? min(($totalPay / (float) $project->total_value) * 100, 100) : 0);

            TeamDistribution::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                ],
                [
                    'role_in_project' => $this->resolvedTeamRole((string) ($projectData['project_number'] ?? '')),
                    'percentage' => number_format(max($percentage, 1), 2, '.', ''),
                    'base_pay' => number_format($assignmentFee, 2, '.', ''),
                    'bonus' => number_format($bonus, 2, '.', ''),
                    'total_pay' => number_format($totalPay, 2, '.', ''),
                    'paid_at' => $relatedPayments
                        ->map(fn (array $payment) => $payment['paid_date'] ?? null)
                        ->filter()
                        ->sort()
                        ->last(),
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function importProjectMaterialsAndStaging(Project $project, array $projectData, int $warehouseId, int $companyId, int $performedByUserId): bool
    {
        $items = collect($projectData['details']['items'] ?? []);
        if ($items->isEmpty()) {
            return false;
        }

        $aggregatedMaterials = [];
        $stagingLines = [];

        foreach ($items as $item) {
            $product = $this->resolveOrCreateMasterProduct($item, $warehouseId);
            $isService = $product->product_type === MasterProduct::PRODUCT_TYPE_SERVICE;
            $plannedQty = (float) ($item['quantity'] ?? 0);
            $lineTotal = (float) ($item['total_price'] ?? ($plannedQty * (float) ($item['price'] ?? 0)));
            $materialKey = $product->id.'|'.$warehouseId;

            if (! isset($aggregatedMaterials[$materialKey])) {
                $aggregatedMaterials[$materialKey] = [
                    'product' => $product,
                    'is_service' => $isService,
                    'planned_qty' => 0.0,
                    'line_total' => 0.0,
                    'unit_price' => 0.0,
                    'notes' => [],
                ];
            }

            $aggregatedMaterials[$materialKey]['planned_qty'] += $plannedQty;
            $aggregatedMaterials[$materialKey]['line_total'] += $lineTotal;
            $aggregatedMaterials[$materialKey]['unit_price'] = (float) ($item['price'] ?? 0);
            $aggregatedMaterials[$materialKey]['notes'][] = 'Imported from legacy item '.($item['legacy_item_id'] ?? '');

            if ($isService) {
                continue;
            }

            $stagingLines[] = [
                'master_product_id' => $product->id,
                'legacy_item_id' => $item['legacy_item_id'] ?? null,
                'legacy_product_sku' => $item['sku'] ?: null,
                'product_name' => $product->name,
                'unit' => $item['unit'] ?: null,
                'qty' => number_format($plannedQty, 2, '.', ''),
                'unit_cost' => number_format((float) ($item['price'] ?? 0), 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'status' => 'draft',
                'notes' => 'Supplier belum dipilih. Dibuat dari legacy import project.',
            ];
        }

        foreach ($aggregatedMaterials as $material) {
            $plannedQty = (float) $material['planned_qty'];
            $lineTotal = (float) $material['line_total'];
            $unitPrice = $plannedQty > 0
                ? $lineTotal / $plannedQty
                : (float) $material['unit_price'];

            ProjectMaterial::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'master_product_id' => $material['product']->id,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'planned_qty' => number_format($plannedQty, 2, '.', ''),
                    'reserved_qty' => 0,
                    'issued_qty' => 0,
                    'unit_cost' => number_format($unitPrice, 2, '.', ''),
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'status' => $material['is_service'] ? 'service' : 'planned',
                    'notes' => implode(' | ', array_unique($material['notes'])),
                ]
            );
        }

        if ($stagingLines === []) {
            ProcurementImportStaging::query()
                ->where('source_import_key', (string) $projectData['import_key'])
                ->delete();

            return false;
        }

        $staging = ProcurementImportStaging::query()->updateOrCreate(
            ['source_import_key' => (string) $projectData['import_key']],
            [
                'project_id' => $project->id,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'legacy_project_number' => (string) $projectData['project_number'],
                'legacy_project_name' => (string) $projectData['title'],
                'procurement_date' => $projectData['sale_date'] ?: now()->toDateString(),
                'status' => 'draft',
                'notes' => 'Staging procurement dari legacy import. Supplier akan dipilih belakangan.',
                'created_by' => $performedByUserId,
            ]
        );

        $staging->lines()->delete();

        foreach ($stagingLines as $line) {
            $staging->lines()->create($line);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    private function resolveOrCreateTechnicianUser(array $assignment, int $companyId): User
    {
        if (! empty($assignment['matched_user']['id'])) {
            return User::query()->findOrFail((int) $assignment['matched_user']['id']);
        }

        $name = trim((string) ($assignment['technician_name'] ?? 'Teknisi Legacy'));
        $email = $this->resolvedLegacyUserEmail($name, (string) ($assignment['legacy_user_email'] ?? ''));

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'company_id' => $companyId,
                'name' => $name,
                'password' => Str::password(24),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveOrCreateMasterProduct(array $item, int $warehouseId): MasterProduct
    {
        if (! empty($item['matched_product']['id'])) {
            return MasterProduct::query()->findOrFail((int) $item['matched_product']['id']);
        }

        $name = trim((string) ($item['name'] ?? 'Legacy Item'));
        $legacySku = strtoupper(trim((string) ($item['sku'] ?? '')));
        $type = $this->resolvedLegacyProductType($name, (string) ($item['unit'] ?? ''));

        $product = MasterProduct::query()
            ->where('name', $name)
            ->first();

        if ($product) {
            return $product;
        }

        return MasterProduct::query()->create([
            'sku' => $this->resolvedMasterProductSku($legacySku, $name),
            'name' => $name,
            'category' => $type === MasterProduct::PRODUCT_TYPE_SERVICE ? 'Service Legacy Import' : 'Project Material Legacy Import',
            'uom' => trim((string) ($item['unit'] ?? 'unit')) ?: 'unit',
            'warehouse_id' => $type === MasterProduct::PRODUCT_TYPE_SERVICE ? null : $warehouseId,
            'sales_channel' => 'project',
            'product_type' => $type,
            'status' => 'active',
            'description' => $legacySku !== '' ? 'Legacy SKU: '.$legacySku : 'Auto-created from legacy import.',
            'selling_price' => number_format((float) ($item['price'] ?? 0), 2, '.', ''),
            'stock' => 0,
        ]);
    }

    private function resolvedLegacyUserEmail(string $name, string $legacyEmail): string
    {
        $legacyEmail = trim(strtolower($legacyEmail));

        if ($legacyEmail !== '' && str_contains($legacyEmail, '@') && ! str_ends_with($legacyEmail, '@technician.local')) {
            return $this->uniqueUserEmail($legacyEmail);
        }

        $base = Str::slug($name, '.');
        $base = $base !== '' ? $base : 'legacy.tech';

        return $this->uniqueUserEmail($base.'@ocnetworks.web.id');
    }

    private function uniqueUserEmail(string $baseEmail): string
    {
        $baseEmail = strtolower($baseEmail);
        if (! User::query()->where('email', $baseEmail)->exists()) {
            return $baseEmail;
        }

        $local = Str::before($baseEmail, '@');
        $domain = Str::after($baseEmail, '@');
        $counter = 2;

        do {
            $candidate = $local.'+legacy'.$counter.'@'.$domain;
            $counter++;
        } while (User::query()->where('email', $candidate)->exists());

        return $candidate;
    }

    private function resolvedLegacyProductType(string $name, string $unit): string
    {
        $haystack = strtolower($name.' '.$unit);

        foreach (['maintenance', 'service', 'jasa', 'visit', 'instalasi', 'pasang', 'setting', 'konfigurasi'] as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return MasterProduct::PRODUCT_TYPE_SERVICE;
            }
        }

        return MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL;
    }

    private function resolvedMasterProductSku(string $legacySku, string $name): string
    {
        if ($legacySku !== '' && ! MasterProduct::query()->where('sku', $legacySku)->exists()) {
            return $legacySku;
        }

        return MasterProduct::generateSku($name);
    }

    private function resolvedProjectType(string $projectNumber, string $title): string
    {
        $normalizedProjectNumber = strtoupper(trim($projectNumber));
        if (str_starts_with($normalizedProjectNumber, 'PRJ-')) {
            return 'cctv_installation';
        }

        if (str_starts_with($normalizedProjectNumber, 'MNT-')) {
            return 'maintenance_network';
        }

        $haystack = strtolower($projectNumber.' '.$title);

        if (str_contains($haystack, 'maintenance')) {
            return 'maintenance_network';
        }

        if (str_contains($haystack, 'cctv') || str_contains($haystack, 'camera') || str_contains($haystack, 'dvr') || str_contains($haystack, 'nvr')) {
            return 'cctv_installation';
        }

        if (str_contains($haystack, 'network') || str_contains($haystack, 'router') || str_contains($haystack, 'access point') || str_contains($haystack, 'kabel')) {
            return 'network_installation';
        }

        return ProjectType::defaultKey();
    }

    private function resolvedTeamRole(string $projectNumber): string
    {
        $normalizedProjectNumber = strtoupper(trim($projectNumber));

        if (str_starts_with($normalizedProjectNumber, 'PRJ-') || str_starts_with($normalizedProjectNumber, 'MNT-')) {
            TeamRole::query()->firstOrCreate(
                ['name' => 'Technician'],
                ['is_active' => true],
            );

            return 'Technician';
        }

        return 'Developer';
    }

    private function resolvedProjectStatus(string $legacyStatus): string
    {
        return match (strtoupper(trim($legacyStatus))) {
            'COMPLETED', 'PAID', 'CLOSED' => 'selesai',
            'APPROVED', 'ONGOING', 'PROCUREMENT' => 'berjalan',
            'CANCELLED' => 'dibatalkan',
            default => 'negosiasi',
        };
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function resolvedFinishedAt(array $projectData): ?string
    {
        $status = $this->resolvedProjectStatus((string) ($projectData['status'] ?? ''));
        if (! in_array($status, ['selesai', 'dibatalkan'], true)) {
            return null;
        }

        return $projectData['last_payment_date'] ?: $projectData['sale_date'] ?: null;
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function legacyDescription(array $projectData): string
    {
        return trim(implode("\n", array_filter([
            'Imported from legacy OCN.',
            'Legacy project number: '.($projectData['project_number'] ?? '-'),
            'QC import key: '.($projectData['import_key'] ?? '-'),
        ])));
    }

    private function resolvedClientContact(?CrmCustomer $crmCustomer): ?string
    {
        if (! $crmCustomer) {
            return null;
        }

        return trim(implode(' / ', array_filter([
            $crmCustomer->phone ?: null,
            $crmCustomer->email ?: null,
        ]))) ?: null;
    }

    /**
     * @param  array<string, mixed>  $assignment
     * @param  array<string, mixed>  $payment
     */
    private function sameTechnician(array $assignment, array $payment): bool
    {
        $assignmentEmail = strtolower(trim((string) ($assignment['legacy_user_email'] ?? '')));
        $paymentEmail = strtolower(trim((string) ($payment['legacy_user_email'] ?? '')));

        if ($assignmentEmail !== '' && $paymentEmail !== '' && $assignmentEmail === $paymentEmail) {
            return true;
        }

        return trim((string) ($assignment['technician_name'] ?? '')) === trim((string) ($payment['technician_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function skipMessage(array $projectData, string $reason): string
    {
        return (string) ($projectData['project_number'] ?? '-').' - '.$reason;
    }
}
