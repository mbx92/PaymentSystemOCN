<?php

namespace App\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Models\Company;
use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\PaymentMethod;
use App\Models\ProcurementImportStaging;
use App\Models\ProductStockMovement;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use App\Models\ProjectType;
use App\Models\TeamDistribution;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyProjectImportService
{
    public function __construct(
        private readonly LegacyProjectSalesQcService $qcService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly GlPostingService $glPostingService,
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

            $procurementGate = $this->procurementGateForProject($projectData);
            if (! $procurementGate['ready']) {
                $skipped[] = $this->skipMessage($projectData, (string) $procurementGate['message']);

                continue;
            }

            DB::transaction(function () use (
                $projectData,
                $company,
                $warehouse,
                $performedByUserId,
                $legacyPaymentsByProject,
                &$createdProjects
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
                $projectPayments = $this->importProjectPayments($project, $legacyPaymentRows, (float) $projectData['expected_value']);
                $this->createLegacyInvoiceAndCashIns($project, $projectPayments, $legacyPaymentRows, $company->id, $performedByUserId, $projectData);
                $this->importTeamAndTechnicianPayments($project, $projectData, $company->id, $performedByUserId);
                $this->attachProcurementStagingToProject($project, $projectData);
                $this->importProjectMaterials($project, $projectData, $warehouse->id);
                $this->issueAvailableStockToLegacyProjectMaterials($project);

                $createdProjects[] = $project->name.' ('.$project->import_key.')';
            });
        }

        return [
            'created_project_count' => count($createdProjects),
            'created_staging_count' => 0,
            'skipped' => $skipped,
            'created_projects' => $createdProjects,
        ];
    }

    /**
     * @param  list<string>  $importKeys
     * @return array<string, mixed>
     */
    public function prepareProcurementStagings(array $importKeys, int $performedByUserId): array
    {
        $importKeys = array_values(array_unique(array_filter(array_map('strval', $importKeys))));

        if ($importKeys === []) {
            throw new RuntimeException('Tidak ada project yang dipilih untuk disiapkan procurement staging-nya.');
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

        $prepared = [];
        $skipped = [];

        foreach ($selectedProjects as $projectData) {
            if (($projectData['readiness'] ?? '') === 'blocked') {
                $skipped[] = $this->skipMessage($projectData, 'Masih berstatus blocked di QC.');

                continue;
            }

            if (! empty($projectData['existing_erp_project'])) {
                $skipped[] = $this->skipMessage($projectData, 'Project dengan import_key yang sama sudah ada di ERP.');

                continue;
            }

            $gate = $this->procurementGateForProject($projectData);
            if (! $gate['requires_procurement']) {
                $skipped[] = $this->skipMessage($projectData, 'Project ini tidak memiliki item stok yang perlu procurement staging.');

                continue;
            }

            if (($gate['staging_status'] ?? null) === 'converted') {
                $skipped[] = $this->skipMessage($projectData, 'Procurement staging sudah converted sebelumnya.');

                continue;
            }

            $stagingPrepared = DB::transaction(function () use ($projectData, $warehouse, $company, $performedByUserId): bool {
                return $this->prepareProcurementStagingFromProjectData(
                    $projectData,
                    $warehouse->id,
                    $company->id,
                    $performedByUserId,
                );
            });

            if (! $stagingPrepared) {
                $skipped[] = $this->skipMessage($projectData, 'Tidak ada line stok yang perlu dibuatkan procurement staging.');

                continue;
            }

            $prepared[] = (string) $projectData['project_number'];
        }

        return [
            'prepared_count' => count($prepared),
            'skipped' => $skipped,
            'prepared_projects' => $prepared,
        ];
    }

    public function backfillMemberPaymentCashOutsForImportKey(string $importKey, int $performedByUserId): array
    {
        $importKey = trim($importKey);
        if ($importKey === '') {
            throw new RuntimeException('Import key wajib diisi.');
        }

        $project = Project::query()
            ->where('import_key', $importKey)
            ->first();

        if (! $project) {
            throw new RuntimeException('Project ERP dengan import key tersebut tidak ditemukan.');
        }

        $report = $this->qcService->buildReport();
        $projectData = collect($report['projects'] ?? [])->firstWhere('import_key', $importKey);

        if (! is_array($projectData)) {
            throw new RuntimeException('Project legacy tidak ditemukan di QC terbaru.');
        }

        $company = Company::query()->where('name', 'OC Networks')->first();
        if (! $company) {
            throw new RuntimeException('Company OC Networks tidak ditemukan di ERP.');
        }

        $createdCount = 0;

        DB::transaction(function () use ($project, $projectData, $company, $performedByUserId, &$createdCount): void {
            $beforeCount = (int) TeamDistribution::query()
                ->where('project_id', $project->id)
                ->whereNotNull('cash_out_id')
                ->count();

            $this->importTeamAndTechnicianPayments($project, $projectData, (int) $company->id, $performedByUserId);

            $afterCount = (int) TeamDistribution::query()
                ->where('project_id', $project->id)
                ->whereNotNull('cash_out_id')
                ->count();

            $createdCount = max($afterCount - $beforeCount, 0);
        });

        return [
            'project_id' => $project->id,
            'created_count' => $createdCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $projectData
     * @return array{requires_procurement: bool, ready: bool, message: string|null, staging_status: string|null}
     */
    public function procurementGateForProject(array $projectData): array
    {
        $staging = ProcurementImportStaging::query()
            ->select(['id', 'status'])
            ->where('source_import_key', (string) ($projectData['import_key'] ?? ''))
            ->first();

        if ($staging) {
            if ($staging->status === 'converted') {
                return [
                    'requires_procurement' => true,
                    'ready' => true,
                    'message' => null,
                    'staging_status' => 'converted',
                ];
            }

            return [
                'requires_procurement' => true,
                'ready' => false,
                'message' => 'Procurement staging sudah ada tetapi belum dikonversi ke PO dan GR.',
                'staging_status' => (string) $staging->status,
            ];
        }

        if (! $this->projectRequiresProcurement($projectData)) {
            return [
                'requires_procurement' => false,
                'ready' => true,
                'message' => null,
                'staging_status' => null,
            ];
        }

        return [
            'requires_procurement' => true,
            'ready' => false,
            'message' => 'Siapkan procurement staging lalu convert ke PO dan GR sebelum import project.',
            'staging_status' => null,
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

    /**
     * @return Collection<int, ProjectPayment>
     */
    private function importProjectPayments(Project $project, Collection $legacyPayments, float $expectedValue): Collection
    {
        if ($legacyPayments->isEmpty()) {
            return collect([
                ProjectPayment::query()->create([
                    'project_id' => $project->id,
                    'term_number' => 1,
                    'percentage' => 100,
                    'amount' => number_format($expectedValue, 2, '.', ''),
                    'paid_at' => null,
                    'note' => 'Imported from legacy without payment record.',
                ]),
            ]);
        }

        $denominator = max($expectedValue, (float) $legacyPayments->sum(fn (object $row) => (float) $row->amount), 1);
        $created = collect();

        foreach ($legacyPayments->values() as $index => $payment) {
            $amount = (float) $payment->amount;

            $created->push(ProjectPayment::query()->create([
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
            ]));
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function importTeamAndTechnicianPayments(Project $project, array $projectData, int $companyId, int $performedByUserId): void
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

            /** @var TeamDistribution $distribution */
            $distribution = TeamDistribution::query()->updateOrCreate(
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
                    'paid_at' => null,
                ]
            );

            $paidPayments = $relatedPayments
                ->filter(fn (array $payment) => trim((string) ($payment['paid_date'] ?? '')) !== '');

            if ($paidPayments->isNotEmpty()) {
                $this->createLegacyMemberPaymentCashOut(
                    $distribution->fresh(['project', 'user']),
                    $paidPayments,
                    $companyId,
                    $performedByUserId,
                );
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paidPayments
     */
    private function createLegacyMemberPaymentCashOut(
        TeamDistribution $distribution,
        Collection $paidPayments,
        int $companyId,
        int $performedByUserId,
    ): void {
        if ($distribution->cash_out_id) {
            return;
        }

        $amount = (float) $paidPayments->sum(fn (array $payment) => (float) ($payment['amount'] ?? 0));
        if ($amount <= 0) {
            return;
        }

        $paymentDate = $paidPayments
            ->map(fn (array $payment) => (string) ($payment['paid_date'] ?? ''))
            ->filter()
            ->sort()
            ->last();

        if (! is_string($paymentDate) || trim($paymentDate) === '') {
            return;
        }

        $cashAccount = Account::defaultCashBankAccount();
        if (! $cashAccount) {
            throw new RuntimeException('Akun kas/bank default tidak ditemukan untuk mencatat pembayaran anggota legacy.');
        }

        $expenseAccountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->where('category', 'biaya_tim')
            ->value('account_id');

        if (! $expenseAccountId) {
            throw new RuntimeException('Kategori cash_out biaya_tim belum di-mapping ke akun CoA.');
        }

        $recipientName = $distribution->user?->name ?? 'Anggota Tim';
        $note = trim(collect([
            'Pembayaran anggota legacy '.$recipientName.' - '.($distribution->project?->name ?? 'Project'),
            'Peran: '.$distribution->role_in_project,
            ...$paidPayments->pluck('payment_number')->filter()->map(fn ($number) => 'Legacy payment: '.$number)->all(),
        ])->filter()->implode(' | '));

        $cashOut = CashOut::query()->create([
            'project_id' => $distribution->project_id,
            'cash_account_id' => $cashAccount->id,
            'category' => 'biaya_tim',
            'amount' => number_format($amount, 2, '.', ''),
            'date' => $paymentDate,
            'note' => $note !== '' ? $note : null,
            'recipient_name' => $recipientName,
            'created_by' => $performedByUserId,
            'document_status' => DocumentStatus::Posted->value,
            'approved_at' => now(),
            'approved_by' => $performedByUserId,
            'posted_at' => now(),
            'posted_by' => $performedByUserId,
        ]);

        $entry = $this->glPostingService->post(
            $companyId,
            sourceModule: 'member_payment',
            sourceReference: (string) $distribution->id,
            description: 'Pembayaran anggota legacy '.$recipientName.' - '.($distribution->project?->name ?? 'Project'),
            entryDate: $paymentDate,
            lines: [
                ['account_id' => (int) $expenseAccountId, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
            ],
        );

        $cashOut->update(['journal_entry_id' => $entry->id]);

        $distribution->update([
            'cash_out_id' => $cashOut->id,
            'paid_at' => $paymentDate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function importProjectMaterials(Project $project, array $projectData, int $warehouseId): void
    {
        $items = collect($projectData['details']['items'] ?? []);
        if ($items->isEmpty()) {
            return;
        }

        $aggregatedMaterials = [];

        foreach ($items as $item) {
            $product = $this->resolveOrCreateMasterProduct($item, $warehouseId);
            $isService = $product->product_type === MasterProduct::PRODUCT_TYPE_SERVICE;
            $plannedQty = (float) ($item['quantity'] ?? 0);
            $lineTotal = $this->resolvedLegacyLinePriceTotal($item, $plannedQty);
            $costTotal = $this->resolvedLegacyLineCostTotal($item, $plannedQty);
            $materialKey = $product->id.'|'.$warehouseId;

            if (! isset($aggregatedMaterials[$materialKey])) {
                $aggregatedMaterials[$materialKey] = [
                    'product' => $product,
                    'is_service' => $isService,
                    'planned_qty' => 0.0,
                    'line_total' => 0.0,
                    'cost_total' => 0.0,
                    'unit_price' => 0.0,
                    'unit_cost' => 0.0,
                    'notes' => [],
                ];
            }

            $aggregatedMaterials[$materialKey]['planned_qty'] += $plannedQty;
            $aggregatedMaterials[$materialKey]['line_total'] += $lineTotal;
            $aggregatedMaterials[$materialKey]['cost_total'] += $costTotal;
            $aggregatedMaterials[$materialKey]['unit_price'] = $this->resolvedLegacyUnitPrice($item);
            $aggregatedMaterials[$materialKey]['unit_cost'] = $this->resolvedLegacyUnitCost($item);
            $aggregatedMaterials[$materialKey]['notes'][] = 'Imported from legacy item '.($item['legacy_item_id'] ?? '');
        }

        $reservationPairs = [];

        foreach ($aggregatedMaterials as $material) {
            $plannedQty = (float) $material['planned_qty'];
            $lineTotal = (float) $material['line_total'];
            $costTotal = (float) $material['cost_total'];
            $unitPrice = $plannedQty > 0
                ? $lineTotal / $plannedQty
                : (float) $material['unit_price'];
            $unitCost = $plannedQty > 0
                ? $costTotal / $plannedQty
                : (float) $material['unit_cost'];

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
                    'unit_cost' => number_format($unitCost, 2, '.', ''),
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'status' => $material['is_service'] ? 'service' : 'planned',
                    'notes' => implode(' | ', array_unique($material['notes'])),
                ]
            );

            if (! $material['is_service']) {
                $reservationPairs[] = [
                    'product_id' => (int) $material['product']->id,
                    'warehouse_id' => $warehouseId,
                ];
            }
        }

        foreach (collect($reservationPairs)->unique(fn (array $pair) => $pair['product_id'].'|'.$pair['warehouse_id']) as $pair) {
            $this->allocateAvailableWarehouseStockToProjectMaterial(
                $project->id,
                (int) $pair['product_id'],
                (int) $pair['warehouse_id'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function prepareProcurementStagingFromProjectData(array $projectData, int $warehouseId, int $companyId, int $performedByUserId): bool
    {
        $items = collect($projectData['details']['items'] ?? []);
        if ($items->isEmpty()) {
            return false;
        }

        $stagingLines = [];

        foreach ($items as $item) {
            $product = $this->resolveOrCreateMasterProduct($item, $warehouseId);
            if ($product->product_type === MasterProduct::PRODUCT_TYPE_SERVICE) {
                continue;
            }

            $plannedQty = (float) ($item['quantity'] ?? 0);
            $lineTotal = $this->resolvedLegacyLinePriceTotal($item, $plannedQty);
            $costTotal = $this->resolvedLegacyLineCostTotal($item, $plannedQty);
            $unitCost = $plannedQty > 0
                ? $costTotal / $plannedQty
                : $this->resolvedLegacyUnitCost($item);

            $stagingLines[] = [
                'master_product_id' => $product->id,
                'legacy_item_id' => $item['legacy_item_id'] ?? null,
                'legacy_product_sku' => $item['sku'] ?: null,
                'product_name' => $product->name,
                'unit' => $item['unit'] ?: null,
                'qty' => number_format($plannedQty, 2, '.', ''),
                'unit_cost' => number_format($unitCost, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'status' => 'draft',
                'notes' => 'Supplier belum dipilih. Dibuat dari legacy import project.',
            ];
        }

        if ($stagingLines === []) {
            return false;
        }

        $staging = ProcurementImportStaging::query()->updateOrCreate(
            ['source_import_key' => (string) $projectData['import_key']],
            [
                'project_id' => null,
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

        if ($staging->status === 'converted') {
            return true;
        }

        $staging->lines()->delete();

        foreach ($stagingLines as $line) {
            $staging->lines()->create($line);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvedLegacyUnitPrice(array $item): float
    {
        return max((float) ($item['price'] ?? 0), 0);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvedLegacyUnitCost(array $item): float
    {
        $cost = (float) ($item['cost'] ?? 0);
        if ($cost > 0) {
            return $cost;
        }

        return $this->resolvedLegacyUnitPrice($item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvedLegacyLinePriceTotal(array $item, float $plannedQty): float
    {
        $lineTotal = (float) ($item['total_price'] ?? 0);
        if ($lineTotal > 0) {
            return $lineTotal;
        }

        return $plannedQty * $this->resolvedLegacyUnitPrice($item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvedLegacyLineCostTotal(array $item, float $plannedQty): float
    {
        $costTotal = (float) ($item['total_cost'] ?? 0);
        if ($costTotal > 0) {
            return $costTotal;
        }

        return $plannedQty * $this->resolvedLegacyUnitCost($item);
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function attachProcurementStagingToProject(Project $project, array $projectData): void
    {
        ProcurementImportStaging::query()
            ->where('source_import_key', (string) ($projectData['import_key'] ?? ''))
            ->update(['project_id' => $project->id]);
    }

    /**
     * @param  array<string, mixed>  $projectData
     */
    private function projectRequiresProcurement(array $projectData): bool
    {
        foreach (($projectData['details']['items'] ?? []) as $item) {
            if ($this->legacyItemRequiresProcurement((array) $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function legacyItemRequiresProcurement(array $item): bool
    {
        $matchedProductId = (int) ($item['matched_product']['id'] ?? 0);
        if ($matchedProductId > 0) {
            $product = MasterProduct::query()->find($matchedProductId);
            if ($product) {
                return $product->isStockTracked();
            }
        }

        return $this->resolvedLegacyProductType(
            (string) ($item['name'] ?? ''),
            (string) ($item['unit'] ?? ''),
        ) !== MasterProduct::PRODUCT_TYPE_SERVICE;
    }

    private function allocateAvailableWarehouseStockToProjectMaterial(string $projectId, int $productId, int $warehouseId): void
    {
        $stockRow = MasterProductWarehouseStock::query()->firstOrCreate(
            ['master_product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['qty' => 0, 'reserved_qty' => 0],
        );

        $existingReservedElsewhere = (float) ProjectMaterial::query()
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('project_id', '!=', $projectId)
            ->sum(DB::raw('GREATEST(reserved_qty - issued_qty, 0)'));

        $availableQty = max((float) $stockRow->qty - $existingReservedElsewhere, 0);

        ProjectMaterial::query()
            ->where('project_id', $projectId)
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->get()
            ->each(function (ProjectMaterial $material) use (&$availableQty): void {
                $plannedQty = (float) $material->planned_qty;
                $reservedQty = min($plannedQty, $availableQty);

                $material->reserved_qty = $reservedQty;
                $material->status = $this->projectMaterialStatus($material);
                $material->save();

                $availableQty = max($availableQty - $reservedQty, 0);
            });

        app(ProjectMaterialReservationService::class)
            ->syncWarehouseReservation($productId, $warehouseId);
    }

    private function issueAvailableStockToLegacyProjectMaterials(Project $project): void
    {
        $movementDate = $project->started_at?->toDateString()
            ?? $project->finished_at?->toDateString()
            ?? now()->toDateString();

        $project->loadMissing(['materials.product']);

        foreach ($project->materials as $material) {
            if (! $material->product?->isStockTracked() || ! $material->warehouse_id) {
                continue;
            }

            $toIssue = max((float) $material->reserved_qty - (float) $material->issued_qty, 0);
            if ($toIssue <= 0) {
                continue;
            }

            $stockRow = MasterProductWarehouseStock::query()->lockForUpdate()->firstOrCreate(
                [
                    'master_product_id' => (int) $material->master_product_id,
                    'warehouse_id' => (int) $material->warehouse_id,
                ],
                ['qty' => 0, 'reserved_qty' => 0],
            );

            $issuedQty = min($toIssue, max((float) $stockRow->qty, 0));
            if ($issuedQty <= 0) {
                continue;
            }

            $stockRow->decrement('qty', $issuedQty);
            $material->issued_qty = (float) $material->issued_qty + $issuedQty;
            $material->status = $this->projectMaterialStatus($material);
            $material->save();

            $material->product()->increment('stock', -1 * $issuedQty);

            ProductStockMovement::query()->create([
                'master_product_id' => $material->master_product_id,
                'warehouse_id' => $material->warehouse_id,
                'movement_date' => $movementDate,
                'movement_type' => 'project_issue_out',
                'qty' => $issuedQty,
                'note' => 'Legacy project issue '.$project->name.' ('.$project->import_key.')',
            ]);

            app(ProjectMaterialReservationService::class)
                ->syncWarehouseReservation((int) $material->master_product_id, (int) $material->warehouse_id);
        }
    }

    private function projectMaterialStatus(ProjectMaterial $material): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
            return 'issued';
        }

        if ($plannedQty > 0 && $reservedQty >= $plannedQty) {
            return 'ready';
        }

        if ($reservedQty > 0) {
            return 'partial';
        }

        return 'planned';
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
     * @param  array<string, mixed>  $projectData
     * @param  Collection<int, ProjectPayment>  $projectPayments
     * @param  Collection<int, object>  $legacyPayments
     */
    private function createLegacyInvoiceAndCashIns(
        Project $project,
        Collection $projectPayments,
        Collection $legacyPayments,
        int $companyId,
        int $performedByUserId,
        array $projectData,
    ): void {
        if (! $project->invoice_number) {
            $invoiceDate = $projectData['sale_date'] ?: $projectData['last_payment_date'] ?: now()->toDateString();
            $project->forceFill([
                'invoice_number' => $this->documentNumberService->next('sales', 'project_invoice', [
                    'prefix' => 'INV-PRJ',
                    'padding_length' => 6,
                ]),
                'invoiced_at' => $invoiceDate,
            ])->save();
        }

        if ($legacyPayments->isEmpty()) {
            return;
        }

        $cashAccount = Account::defaultCashBankAccount();
        if (! $cashAccount) {
            throw new RuntimeException('Akun kas/bank default tidak ditemukan untuk mencatat pembayaran invoice legacy.');
        }

        $paymentMethodMap = PaymentMethod::query()
            ->where('status', 'active')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(function (PaymentMethod $method): array {
                return [
                    strtolower($method->code) => $method,
                    strtolower($method->name) => $method,
                ];
            });

        $revenueAccount = app(CoaSettingService::class)->resolveAccountByKey('project_invoice_revenue_account', '4003');

        foreach ($projectPayments->values() as $index => $projectPayment) {
            $legacyPayment = $legacyPayments->values()->get($index);
            if (! $legacyPayment instanceof \stdClass || ! $projectPayment->paid_at) {
                continue;
            }

            $paymentMethod = $this->resolveLegacyPaymentMethod((string) ($legacyPayment->method ?? ''), $paymentMethodMap);
            $paymentDate = $projectPayment->paid_at->toDateString();
            $amount = (float) $projectPayment->amount;

            $cashIn = CashIn::query()->updateOrCreate(
                ['project_payment_id' => $projectPayment->id],
                [
                    'project_id' => $project->id,
                    'payment_method_id' => $paymentMethod?->id,
                    'cash_account_id' => $cashAccount->id,
                    'category' => 'pendapatan_project',
                    'amount' => number_format($amount, 2, '.', ''),
                    'document_status' => DocumentStatus::Posted->value,
                    'approved_at' => now(),
                    'approved_by' => $performedByUserId,
                    'posted_at' => now(),
                    'posted_by' => $performedByUserId,
                    'date' => $paymentDate,
                    'note' => trim(implode(' | ', array_filter([
                        'Legacy invoice payment '.$project->invoice_number,
                        $legacyPayment->payment_number ? 'Payment #: '.$legacyPayment->payment_number : null,
                        $legacyPayment->method ? 'Method: '.$legacyPayment->method : null,
                        $legacyPayment->notes ?: null,
                    ]))) ?: null,
                    'created_by' => $performedByUserId,
                ]
            );

            if ($cashIn->journal_entry_id) {
                continue;
            }

            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'project_invoice_payment',
                sourceReference: (string) $cashIn->id,
                description: 'Pembayaran invoice legacy project '.$project->name,
                entryDate: $paymentDate,
                lines: [
                    ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            $cashIn->update(['journal_entry_id' => $entry->id]);
        }
    }

    /**
     * @param  Collection<string, PaymentMethod>  $paymentMethodMap
     */
    private function resolveLegacyPaymentMethod(string $legacyMethod, Collection $paymentMethodMap): ?PaymentMethod
    {
        $legacyMethod = strtolower(trim($legacyMethod));

        foreach ([
            'transfer' => ['transfer', 'trf', 'bank', 'bca', 'bri'],
            'qris' => ['qris', 'qr'],
            'cash' => ['cash', 'tunai'],
        ] as $target => $keywords) {
            if (collect($keywords)->contains(fn (string $keyword) => str_contains($legacyMethod, $keyword))) {
                return $paymentMethodMap->get($target) ?? $paymentMethodMap->first();
            }
        }

        return $paymentMethodMap->first();
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
