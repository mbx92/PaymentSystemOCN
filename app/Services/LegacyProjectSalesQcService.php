<?php

namespace App\Services;

use App\ERP\CRM\Models\CrmCustomer;
use App\Models\MasterProduct;
use App\Models\ProcurementImportStaging;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyProjectSalesQcService
{
    private const CONNECTION = 'legacy_ocn';

    private const ISSUE_CATALOG = [
        'Nama project kosong.' => [
            'code' => 'project_name_empty',
            'label' => 'Nama project kosong',
            'fix_hint' => 'Isi judul/nama project di sistem lama atau tentukan nama final saat staging import.',
        ],
        'Customer tidak ditemukan atau nama customer kosong.' => [
            'code' => 'customer_missing',
            'label' => 'Customer tidak valid',
            'fix_hint' => 'Pastikan project terhubung ke customer yang benar dan nama customer tidak kosong.',
        ],
        'Tanggal penjualan tidak bisa ditentukan dari startDate maupun createdAt.' => [
            'code' => 'sale_date_missing',
            'label' => 'Tanggal penjualan tidak ditemukan',
            'fix_hint' => 'Isi startDate. Jika transaksi historis, pastikan tanggal jual aslinya tersedia sebelum import.',
        ],
        'Nilai project harus lebih besar dari 0.' => [
            'code' => 'project_value_zero',
            'label' => 'Nilai project nol',
            'fix_hint' => 'Isi budget/finalPrice sesuai nilai penjualan real agar revenue ERP tidak nol.',
        ],
        'Project belum memiliki payment real di data legacy.' => [
            'code' => 'project_without_payment',
            'label' => 'Project belum punya payment',
            'fix_hint' => 'Cek apakah memang belum dibayar atau payment belum diinput. Jika sudah dibayar, lengkapi paymentDate dan amount.',
        ],
        'Data customer belum lengkap (phone/alamat kosong).' => [
            'code' => 'customer_incomplete',
            'label' => 'Customer belum lengkap',
            'fix_hint' => 'Lengkapi minimal nomor telepon dan alamat customer sebelum data dipromosikan ke ERP.',
        ],
        'Ada payment dengan amount <= 0.' => [
            'code' => 'payment_non_positive',
            'label' => 'Nominal payment tidak valid',
            'fix_hint' => 'Perbaiki nominal payment agar lebih besar dari 0 atau hapus transaksi yang salah input.',
        ],
        'Ada payment tanpa paymentDate.' => [
            'code' => 'payment_date_missing',
            'label' => 'Tanggal payment kosong',
            'fix_hint' => 'Isi paymentDate dengan tanggal penerimaan uang yang real.',
        ],
        'Ada paymentDate yang lebih awal dari tanggal penjualan project.' => [
            'code' => 'payment_before_sale_date',
            'label' => 'Payment lebih awal dari tanggal jual',
            'fix_hint' => 'Cek ulang startDate project atau paymentDate. Salah satu tanggal kemungkinan tidak real.',
        ],
        'Total payment belum sama dengan nilai project.' => [
            'code' => 'payment_below_project_value',
            'label' => 'Total payment kurang dari nilai project',
            'fix_hint' => 'Tentukan apakah ini masih piutang, diskon, atau memang nilai project yang harus dikoreksi.',
        ],
        'Project berstatus CANCELLED tetapi tetap memiliki payment.' => [
            'code' => 'cancelled_with_payment',
            'label' => 'Project cancel tetapi ada payment',
            'fix_hint' => 'Tentukan apakah status project harus diubah atau payment perlu direlokasi/dibatalkan.',
        ],
        'Project dengan import_key yang sama sudah ada di ERP.' => [
            'code' => 'duplicate_import_key',
            'label' => 'Sudah pernah diimport',
            'fix_hint' => 'Cek project di ERP. Jika itu data yang sama, lakukan update terkontrol, jangan import sebagai data baru.',
        ],
    ];

    public function buildReport(): array
    {
        $connection = DB::connection(self::CONNECTION);
        $config = (array) config('database.connections.'.self::CONNECTION, []);

        $hasUrl = trim((string) ($config['url'] ?? '')) !== '';
        $hasDatabase = trim((string) ($config['database'] ?? '')) !== '';

        if (! $hasUrl && ! $hasDatabase) {
            throw new RuntimeException('Koneksi legacy belum dikonfigurasi. Isi LEGACY_DB_* pada environment ERP terlebih dahulu.');
        }

        try {
            $connection->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal terhubung ke database legacy: '.$e->getMessage(), previous: $e);
        }

        $projects = collect($connection->select(<<<'SQL'
            SELECT
                p.id,
                p."projectNumber" AS project_number,
                p.title,
                p.status,
                p.budget,
                p."finalPrice" AS final_price,
                p."createdAt" AS created_at,
                p."startDate" AS start_date,
                p."endDate" AS end_date,
                p."customerId" AS customer_id,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.address AS customer_address
            FROM "Project" p
            LEFT JOIN "Customer" c ON c.id = p."customerId"
            ORDER BY p."createdAt", p."projectNumber"
        SQL));

        $payments = collect($connection->select(<<<'SQL'
            SELECT
                id,
                "projectId" AS project_id,
                amount,
                "paymentNumber" AS payment_number,
                status,
                type,
                mode,
                "paymentDate" AS payment_date,
                "paidDate" AS paid_date,
                "createdAt" AS created_at
            FROM "Payment"
            ORDER BY "paymentDate", "paymentNumber"
        SQL));

        $projectItems = collect($connection->select(<<<'SQL'
            SELECT
                pi.id,
                pi."projectId" AS project_id,
                pi."productId" AS product_id,
                pi.name,
                pi.quantity,
                pi.unit,
                pi.price,
                pi."totalPrice" AS total_price,
                pi.cost,
                pi."totalCost" AS total_cost,
                pi.type,
                p.sku AS product_sku,
                p.name AS product_name,
                p.category AS product_category
            FROM "ProjectItem" pi
            LEFT JOIN "Product" p ON p.id = pi."productId"
            ORDER BY pi."projectId", pi."addedAt", pi.name
        SQL));

        $projectTechnicians = collect($connection->select(<<<'SQL'
            SELECT
                pt.id,
                pt."projectId" AS project_id,
                pt."technicianId" AS technician_id,
                pt.fee,
                pt."feeType" AS fee_type,
                pt."isPaid" AS is_paid,
                pt."paidDate" AS paid_date,
                pt.notes,
                t.name AS technician_name,
                t.phone AS technician_phone,
                t."userId" AS legacy_user_id,
                u.email AS legacy_user_email
            FROM "ProjectTechnician" pt
            INNER JOIN "Technician" t ON t.id = pt."technicianId"
            LEFT JOIN "User" u ON u.id = t."userId"
            ORDER BY pt."projectId", t.name
        SQL));

        $technicianPayments = collect($connection->select(<<<'SQL'
            SELECT
                tp.id,
                tp."projectId" AS project_id,
                tp."technicianId" AS technician_id,
                tp."paymentNumber" AS payment_number,
                tp.period,
                tp.amount,
                tp.status,
                tp."paidDate" AS paid_date,
                tp.description,
                tp.notes,
                t.name AS technician_name,
                t.phone AS technician_phone,
                t."userId" AS legacy_user_id,
                u.email AS legacy_user_email
            FROM "TechnicianPayment" tp
            INNER JOIN "Technician" t ON t.id = tp."technicianId"
            LEFT JOIN "User" u ON u.id = t."userId"
            ORDER BY tp."projectId", tp."paidDate", tp."paymentNumber"
        SQL));

        $paymentsByProject = $payments->groupBy(fn (object $payment) => (string) $payment->project_id);
        $projectItemsByProject = $projectItems->groupBy(fn (object $item) => (string) $item->project_id);
        $projectTechniciansByProject = $projectTechnicians->groupBy(fn (object $assignment) => (string) $assignment->project_id);
        $technicianPaymentsByProject = $technicianPayments->groupBy(fn (object $payment) => (string) $payment->project_id);
        $existingImportKeys = Project::query()
            ->whereNotNull('import_key')
            ->pluck('id', 'import_key');
        $crmCustomerNameMap = $this->crmCustomerNameMap();
        $erpProductMaps = $this->erpProductMaps();
        $erpUserMaps = $this->erpUserMaps();
        $existingProjectsByImportKey = Project::query()
            ->with([
                'materials.product:id,sku,name',
                'teamDistributions.user:id,name,email',
            ])
            ->whereIn('import_key', $projects->map(fn (object $project) => 'ocn1-project:'.$project->id)->all())
            ->get()
            ->keyBy('import_key');
        $stagingByImportKey = ProcurementImportStaging::query()
            ->whereIn('source_import_key', $projects->map(fn (object $project) => 'ocn1-project:'.$project->id)->all())
            ->get()
            ->keyBy('source_import_key');

        $projectRows = [];
        $issues = [];
        $readyCount = 0;
        $warningCount = 0;
        $blockedCount = 0;
        $warningIssueCount = 0;
        $errorIssueCount = 0;
        $projectsWithoutPayments = 0;
        $paymentMismatchCount = 0;
        $legacyProjectTotal = 0.0;
        $legacyPaidTotal = 0.0;

        foreach ($projects as $project) {
            $projectPayments = $paymentsByProject->get((string) $project->id, collect())
                ->sortBy(fn (object $payment) => (string) ($payment->payment_date ?? $payment->created_at ?? ''));

            $saleDate = $this->resolveSaleDate($project);
            $saleDateSource = $saleDate === null
                ? null
                : ($project->start_date ? 'startDate' : 'createdAt');
            $paidTotal = round(
                $projectPayments->sum(fn (object $payment) => $this->toFloat($payment->amount)),
                2
            );
            $isMaintenanceProject = $this->isMaintenanceProject($project);
            [$expectedValue, $expectedValueSource] = $this->resolveExpectedValue($project, $paidTotal, $isMaintenanceProject);
            $crmCustomerMatch = $this->matchCrmCustomer((string) ($project->customer_name ?? ''), $crmCustomerNameMap);
            $projectImportKey = 'ocn1-project:'.$project->id;
            $existingErpProject = $existingProjectsByImportKey->get($projectImportKey);
            $procurementStaging = $stagingByImportKey->get($projectImportKey);
            $importStatus = $this->resolvedImportStatus($existingErpProject, $procurementStaging);
            $itemCompare = $this->buildProjectItemCompare(
                $projectItemsByProject->get((string) $project->id, collect()),
                $erpProductMaps,
                $existingErpProject
            );
            $technicianCompare = $this->buildProjectTechnicianCompare(
                $projectTechniciansByProject->get((string) $project->id, collect()),
                $erpUserMaps,
                $existingErpProject
            );
            $technicianPaymentCompare = $this->buildTechnicianPaymentCompare(
                $technicianPaymentsByProject->get((string) $project->id, collect()),
                $erpUserMaps,
                $existingErpProject
            );

            $legacyProjectTotal += $expectedValue;
            $legacyPaidTotal += $paidTotal;

            $projectErrors = [];
            $projectWarnings = [];

            if (trim((string) $project->title) === '') {
                $projectErrors[] = 'Nama project kosong.';
            }

            if (trim((string) $project->customer_name) === '') {
                $projectErrors[] = 'Customer tidak ditemukan atau nama customer kosong.';
            }

            if ($saleDate === null) {
                $projectErrors[] = 'Tanggal penjualan tidak bisa ditentukan dari startDate maupun createdAt.';
            }

            if ($expectedValue <= 0) {
                $projectErrors[] = 'Nilai project harus lebih besar dari 0.';
            }

            if ($projectPayments->isEmpty()) {
                $projectsWithoutPayments++;
                if (strtoupper((string) $project->status) !== 'CANCELLED') {
                    $projectWarnings[] = 'Project belum memiliki payment real di data legacy.';
                }
            }

            if (
                (trim((string) $project->customer_phone) === '' || trim((string) $project->customer_address) === '')
                && $crmCustomerMatch === null
            ) {
                $projectWarnings[] = 'Data customer belum lengkap (phone/alamat kosong).';
            }

            foreach ($projectPayments as $payment) {
                $amount = $this->toFloat($payment->amount);
                $paymentDate = $this->parseDateValue($payment->payment_date);

                if ($amount <= 0) {
                    $projectErrors[] = 'Ada payment dengan amount <= 0.';
                }

                if ($paymentDate === null) {
                    $projectErrors[] = 'Ada payment tanpa paymentDate.';

                    continue;
                }

                if ($saleDate !== null && $paymentDate->lt($saleDate)) {
                    $projectErrors[] = 'Ada paymentDate yang lebih awal dari tanggal penjualan project.';
                }
            }

            if ($expectedValue > 0 && $paidTotal > 0 && abs($expectedValue - $paidTotal) > 0.01 && $paidTotal < $expectedValue) {
                $paymentMismatchCount++;
                $projectWarnings[] = 'Total payment belum sama dengan nilai project.';
            }

            if (strtoupper((string) $project->status) === 'CANCELLED' && $paidTotal > 0) {
                $projectWarnings[] = 'Project berstatus CANCELLED tetapi tetap memiliki payment.';
            }

            if ($existingImportKeys->has($projectImportKey)) {
                $projectWarnings[] = 'Project dengan import_key yang sama sudah ada di ERP.';
            }

            $projectErrors = array_values(array_unique($projectErrors));
            $projectWarnings = array_values(array_unique($projectWarnings));

            $projectErrorDetails = array_map(fn (string $message) => $this->issueRow('error', $project, $message), $projectErrors);
            $projectWarningDetails = array_map(fn (string $message) => $this->issueRow('warning', $project, $message), $projectWarnings);
            $projectIssueDetails = array_values([...$projectErrorDetails, ...$projectWarningDetails]);

            foreach ($projectErrorDetails as $issue) {
                $errorIssueCount++;
                $issues[] = $issue;
            }

            foreach ($projectWarningDetails as $issue) {
                $warningIssueCount++;
                $issues[] = $issue;
            }

            $readiness = 'ready';
            if ($projectErrors !== []) {
                $readiness = 'blocked';
                $blockedCount++;
            } elseif ($projectWarnings !== []) {
                $readiness = 'warning';
                $warningCount++;
            } else {
                $readyCount++;
            }

            $projectRows[] = [
                'legacy_id' => (string) $project->id,
                'import_key' => $projectImportKey,
                'project_number' => (string) ($project->project_number ?? '-'),
                'title' => (string) ($project->title ?? ''),
                'customer_name' => (string) ($project->customer_name ?? ''),
                'crm_customer_match' => $crmCustomerMatch,
                'status' => (string) ($project->status ?? ''),
                'existing_erp_project' => $existingErpProject ? [
                    'id' => $existingErpProject->id,
                    'name' => $existingErpProject->name,
                    'status' => $existingErpProject->status,
                ] : null,
                'import_status' => $importStatus,
                'is_importable' => in_array($importStatus['key'], ['pending_import', 'procurement_converted_pending_project'], true),
                'sale_date' => $saleDate?->toDateString(),
                'sale_date_source' => $saleDateSource,
                'expected_value_source' => $expectedValueSource,
                'expected_value' => round($expectedValue, 2),
                'paid_total' => round($paidTotal, 2),
                'payment_count' => $projectPayments->count(),
                'readiness' => $readiness,
                'issues_count' => count($projectErrors) + count($projectWarnings),
                'last_payment_date' => $this->resolveLastPaymentDate($projectPayments),
                'issues' => $projectIssueDetails,
                'compare_summary' => [
                    'items_total' => $itemCompare['summary']['total'],
                    'items_unresolved' => $itemCompare['summary']['unresolved'],
                    'technicians_total' => $technicianCompare['summary']['total'],
                    'technicians_unresolved' => $technicianCompare['summary']['unresolved'],
                    'technician_payments_total' => $technicianPaymentCompare['summary']['total'],
                    'technician_payments_unresolved' => $technicianPaymentCompare['summary']['unresolved'],
                ],
                'details' => [
                    'items' => $itemCompare['rows'],
                    'technicians' => $technicianCompare['rows'],
                    'technician_payments' => $technicianPaymentCompare['rows'],
                ],
            ];
        }

        usort($projectRows, function (array $left, array $right): int {
            $leftDate = (string) ($left['sale_date'] ?? '');
            $rightDate = (string) ($right['sale_date'] ?? '');

            if ($leftDate === '' && $rightDate !== '') {
                return 1;
            }

            if ($leftDate !== '' && $rightDate === '') {
                return -1;
            }

            if ($leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            return strcmp((string) ($left['project_number'] ?? ''), (string) ($right['project_number'] ?? ''));
        });

        usort($issues, function (array $left, array $right): int {
            $rank = ['error' => 0, 'warning' => 1];
            $leftRank = $rank[$left['severity']] ?? 99;
            $rightRank = $rank[$right['severity']] ?? 99;

            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return strcmp((string) ($left['project_number'] ?? ''), (string) ($right['project_number'] ?? ''));
        });

        $issueGroups = $this->buildIssueGroups($issues);

        return [
            'generated_at' => now()->toDateTimeString(),
            'source' => [
                'connection' => self::CONNECTION,
                'host' => (string) ($config['host'] ?? ''),
                'database' => (string) ($config['database'] ?? ''),
                'schema' => (string) ($config['search_path'] ?? 'public'),
            ],
            'scope' => [
                'included' => ['Project', 'Customer', 'Payment'],
                'ignored' => ['PurchaseOrder', 'PurchaseOrderItem', 'Stock', 'CashTransaction'],
                'date_rules' => [
                    'project_sale_date' => 'startDate jika ada, fallback ke createdAt',
                    'payment_real_date' => 'paymentDate',
                ],
            ],
            'summary' => [
                'total_projects' => count($projectRows),
                'total_payments' => $payments->count(),
                'ready_projects' => $readyCount,
                'warning_projects' => $warningCount,
                'blocked_projects' => $blockedCount,
                'projects_without_payments' => $projectsWithoutPayments,
                'payment_mismatch_projects' => $paymentMismatchCount,
                'warning_issues' => $warningIssueCount,
                'error_issues' => $errorIssueCount,
                'legacy_project_total' => round($legacyProjectTotal, 2),
                'legacy_paid_total' => round($legacyPaidTotal, 2),
                'import_status_counts' => collect($projectRows)
                    ->countBy(fn (array $project) => (string) ($project['import_status']['key'] ?? 'pending_import'))
                    ->all(),
            ],
            'issue_groups' => $issueGroups,
            'issues' => $issues,
            'projects' => $projectRows,
        ];
    }

    private function resolveSaleDate(object $project): ?Carbon
    {
        return $this->parseDateValue($project->start_date)
            ?? $this->parseDateValue($project->created_at);
    }

    private function resolveExpectedValue(object $project, float $paidTotal, bool $isMaintenanceProject): array
    {
        if ($isMaintenanceProject && $paidTotal > 0) {
            return [$paidTotal, 'paymentTotal'];
        }

        $finalPrice = $this->toFloat($project->final_price);
        if ($finalPrice > 0) {
            return [$finalPrice, 'finalPrice'];
        }

        $budget = $this->toFloat($project->budget);
        if ($budget > 0) {
            return [$budget, 'budget'];
        }

        if ($paidTotal > 0) {
            return [$paidTotal, 'paymentTotalFallback'];
        }

        return [0.0, 'missing'];
    }

    private function resolveLastPaymentDate(Collection $payments): ?string
    {
        $last = $payments
            ->map(fn (object $payment) => $this->parseDateValue($payment->payment_date))
            ->filter()
            ->sort()
            ->last();

        return $last instanceof Carbon ? $last->toDateString() : null;
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function isMaintenanceProject(object $project): bool
    {
        $projectNumber = strtoupper(trim((string) ($project->project_number ?? '')));
        $title = strtoupper(trim((string) ($project->title ?? '')));

        return str_starts_with($projectNumber, 'MNT-')
            || str_starts_with($title, 'MAINTENANCE:');
    }

    private function resolvedImportStatus(?Project $existingErpProject, ?ProcurementImportStaging $procurementStaging): array
    {
        if (! $existingErpProject && ! $procurementStaging) {
            return [
                'key' => 'pending_import',
                'label' => 'Belum diimport',
                'description' => 'Project legacy belum dibuat di ERP.',
                'badge' => 'badge-ghost',
            ];
        }

        if ($existingErpProject && ! $procurementStaging) {
            return [
                'key' => 'imported_project_only',
                'label' => 'Sudah diimport',
                'description' => 'Project ERP sudah ada, tanpa procurement staging.',
                'badge' => 'badge-info',
            ];
        }

        if (! $existingErpProject && $procurementStaging?->status === 'converted') {
            return [
                'key' => 'procurement_converted_pending_project',
                'label' => 'Procurement selesai',
                'description' => 'PO/GR legacy sudah dibuat dan diposting. Project ERP belum dibuat dan siap diimport.',
                'badge' => 'badge-primary',
            ];
        }

        if ($procurementStaging?->status === 'converted') {
            return [
                'key' => 'imported_with_procurement_converted',
                'label' => 'PO/GR dibuat',
                'description' => 'Project sudah diimport dan procurement sudah dikonversi ke PO/GR.',
                'badge' => 'badge-success',
            ];
        }

        if ($procurementStaging) {
            return [
                'key' => 'imported_with_procurement_staging',
                'label' => 'Draft procurement',
                'description' => 'Project sudah diimport dan menunggu review procurement staging.',
                'badge' => 'badge-warning',
            ];
        }

        return [
            'key' => 'pending_import',
            'label' => 'Belum diimport',
            'description' => 'Project legacy belum dibuat di ERP.',
            'badge' => 'badge-ghost',
        ];
    }

    /**
     * @return array<string, array{id: int, name: string}>
     */
    private function crmCustomerNameMap(): array
    {
        $map = [];

        CrmCustomer::query()
            ->select(['id', 'name', 'company'])
            ->orderBy('id')
            ->chunk(200, function (Collection $customers) use (&$map): void {
                foreach ($customers as $customer) {
                    foreach ([(string) $customer->name, (string) ($customer->company ?? '')] as $candidate) {
                        $normalized = $this->normalizeCustomerName($candidate);
                        if ($normalized === '' || isset($map[$normalized])) {
                            continue;
                        }

                        $map[$normalized] = [
                            'id' => (int) $customer->id,
                            'name' => (string) $customer->name,
                        ];
                    }
                }
            });

        return $map;
    }

    /**
     * @param  array<string, array{id: int, name: string}>  $crmCustomerNameMap
     * @return array{id: int, name: string}|null
     */
    private function matchCrmCustomer(string $legacyCustomerName, array $crmCustomerNameMap): ?array
    {
        $normalized = $this->normalizeCustomerName($legacyCustomerName);
        if ($normalized === '') {
            return null;
        }

        return $crmCustomerNameMap[$normalized] ?? null;
    }

    private function normalizeCustomerName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array{by_sku: array<string, array{id: int, sku: string, name: string}>, by_name: array<string, array{id: int, sku: string, name: string}>}
     */
    private function erpProductMaps(): array
    {
        $bySku = [];
        $byName = [];

        MasterProduct::query()
            ->select(['id', 'sku', 'name'])
            ->orderBy('id')
            ->chunk(200, function (Collection $products) use (&$bySku, &$byName): void {
                foreach ($products as $product) {
                    $skuKey = $this->normalizeSku((string) $product->sku);
                    $nameKey = $this->normalizeCustomerName((string) $product->name);

                    if ($skuKey !== '' && ! isset($bySku[$skuKey])) {
                        $bySku[$skuKey] = [
                            'id' => (int) $product->id,
                            'sku' => (string) $product->sku,
                            'name' => (string) $product->name,
                        ];
                    }

                    if ($nameKey !== '' && ! isset($byName[$nameKey])) {
                        $byName[$nameKey] = [
                            'id' => (int) $product->id,
                            'sku' => (string) $product->sku,
                            'name' => (string) $product->name,
                        ];
                    }
                }
            });

        return ['by_sku' => $bySku, 'by_name' => $byName];
    }

    /**
     * @return array{by_email: array<string, array{id: int, name: string, email: string}>, by_name: array<string, array{id: int, name: string, email: string}>}
     */
    private function erpUserMaps(): array
    {
        $byEmail = [];
        $byName = [];

        User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->chunk(200, function (Collection $users) use (&$byEmail, &$byName): void {
                foreach ($users as $user) {
                    $emailKey = mb_strtolower(trim((string) $user->email));
                    $nameKey = $this->normalizeCustomerName((string) $user->name);

                    $payload = [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                    ];

                    if ($emailKey !== '' && ! isset($byEmail[$emailKey])) {
                        $byEmail[$emailKey] = $payload;
                    }

                    if ($nameKey !== '' && ! isset($byName[$nameKey])) {
                        $byName[$nameKey] = $payload;
                    }
                }
            });

        return ['by_email' => $byEmail, 'by_name' => $byName];
    }

    /**
     * @param  Collection<int, object>  $legacyItems
     * @param  array{by_sku: array<string, array{id: int, sku: string, name: string}>, by_name: array<string, array{id: int, sku: string, name: string}>}  $erpProductMaps
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildProjectItemCompare(Collection $legacyItems, array $erpProductMaps, ?Project $existingErpProject): array
    {
        $rows = [];
        $unresolved = 0;

        foreach ($legacyItems as $item) {
            $matchedProduct = $this->matchErpProduct($item, $erpProductMaps);
            $existingMaterial = null;

            if ($matchedProduct !== null && $existingErpProject) {
                $existingMaterial = $existingErpProject->materials
                    ->first(fn ($material) => (int) $material->master_product_id === (int) $matchedProduct['id']);
            }

            $status = 'unresolved';
            if ($existingMaterial) {
                $status = 'already_in_erp_project';
            } elseif ($matchedProduct !== null) {
                $status = 'matched_master_product';
            } else {
                $unresolved++;
            }

            $rows[] = [
                'legacy_item_id' => (string) $item->id,
                'name' => (string) ($item->name ?? ''),
                'sku' => (string) ($item->product_sku ?? ''),
                'unit' => (string) ($item->unit ?? ''),
                'quantity' => (float) ($item->quantity ?? 0),
                'price' => (float) ($item->price ?? 0),
                'total_price' => (float) ($item->total_price ?? 0),
                'cost' => (float) ($item->cost ?? 0),
                'total_cost' => (float) ($item->total_cost ?? 0),
                'type' => (string) ($item->type ?? ''),
                'status' => $status,
                'matched_product' => $matchedProduct,
                'existing_material' => $existingMaterial ? [
                    'id' => $existingMaterial->id,
                    'planned_qty' => (float) $existingMaterial->planned_qty,
                    'unit_cost' => (float) $existingMaterial->unit_cost,
                    'unit_price' => (float) $existingMaterial->unit_price,
                ] : null,
            ];
        }

        return [
            'summary' => [
                'total' => count($rows),
                'unresolved' => $unresolved,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyTechnicians
     * @param  array{by_email: array<string, array{id: int, name: string, email: string}>, by_name: array<string, array{id: int, name: string, email: string}>}  $erpUserMaps
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildProjectTechnicianCompare(Collection $legacyTechnicians, array $erpUserMaps, ?Project $existingErpProject): array
    {
        $rows = [];
        $unresolved = 0;

        foreach ($legacyTechnicians as $assignment) {
            $matchedUser = $this->matchErpUser(
                (string) ($assignment->legacy_user_email ?? ''),
                (string) ($assignment->technician_name ?? ''),
                $erpUserMaps
            );

            $existingTeamDistribution = null;
            if ($matchedUser !== null && $existingErpProject) {
                $existingTeamDistribution = $existingErpProject->teamDistributions
                    ->first(fn ($distribution) => (int) $distribution->user_id === (int) $matchedUser['id']);
            }

            $status = 'unresolved';
            if ($existingTeamDistribution) {
                $status = 'already_in_erp_project';
            } elseif ($matchedUser !== null) {
                $status = 'matched_erp_user';
            } else {
                $unresolved++;
            }

            $rows[] = [
                'legacy_assignment_id' => (string) $assignment->id,
                'legacy_technician_id' => (string) $assignment->technician_id,
                'technician_name' => (string) ($assignment->technician_name ?? ''),
                'technician_phone' => (string) ($assignment->technician_phone ?? ''),
                'legacy_user_email' => (string) ($assignment->legacy_user_email ?? ''),
                'fee' => (float) ($assignment->fee ?? 0),
                'fee_type' => (string) ($assignment->fee_type ?? ''),
                'is_paid' => (bool) ($assignment->is_paid ?? false),
                'paid_date' => $this->parseDateValue($assignment->paid_date)?->toDateString(),
                'status' => $status,
                'matched_user' => $matchedUser,
                'existing_team_distribution' => $existingTeamDistribution ? [
                    'id' => $existingTeamDistribution->id,
                    'role_in_project' => $existingTeamDistribution->role_in_project,
                    'total_pay' => (float) $existingTeamDistribution->total_pay,
                    'paid_at' => optional($existingTeamDistribution->paid_at)?->format('Y-m-d H:i:s'),
                ] : null,
            ];
        }

        return [
            'summary' => [
                'total' => count($rows),
                'unresolved' => $unresolved,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyPayments
     * @param  array{by_email: array<string, array{id: int, name: string, email: string}>, by_name: array<string, array{id: int, name: string, email: string}>}  $erpUserMaps
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildTechnicianPaymentCompare(Collection $legacyPayments, array $erpUserMaps, ?Project $existingErpProject): array
    {
        $rows = [];
        $unresolved = 0;

        foreach ($legacyPayments as $payment) {
            $matchedUser = $this->matchErpUser(
                (string) ($payment->legacy_user_email ?? ''),
                (string) ($payment->technician_name ?? ''),
                $erpUserMaps
            );

            $existingTeamDistribution = null;
            if ($matchedUser !== null && $existingErpProject) {
                $existingTeamDistribution = $existingErpProject->teamDistributions
                    ->first(fn ($distribution) => (int) $distribution->user_id === (int) $matchedUser['id']);
            }

            $status = 'unresolved';
            if ($existingTeamDistribution && $existingTeamDistribution->paid_at !== null) {
                $status = 'already_paid_in_erp';
            } elseif ($existingTeamDistribution) {
                $status = 'matched_existing_distribution';
            } elseif ($matchedUser !== null) {
                $status = 'matched_erp_user';
            } else {
                $unresolved++;
            }

            $rows[] = [
                'legacy_payment_id' => (string) $payment->id,
                'payment_number' => (string) ($payment->payment_number ?? ''),
                'technician_name' => (string) ($payment->technician_name ?? ''),
                'legacy_user_email' => (string) ($payment->legacy_user_email ?? ''),
                'period' => (string) ($payment->period ?? ''),
                'amount' => (float) ($payment->amount ?? 0),
                'status_label' => (string) ($payment->status ?? ''),
                'paid_date' => $this->parseDateValue($payment->paid_date)?->toDateString(),
                'description' => (string) ($payment->description ?? ''),
                'status' => $status,
                'matched_user' => $matchedUser,
                'existing_team_distribution' => $existingTeamDistribution ? [
                    'id' => $existingTeamDistribution->id,
                    'total_pay' => (float) $existingTeamDistribution->total_pay,
                    'paid_at' => optional($existingTeamDistribution->paid_at)?->format('Y-m-d H:i:s'),
                ] : null,
            ];
        }

        return [
            'summary' => [
                'total' => count($rows),
                'unresolved' => $unresolved,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{by_sku: array<string, array{id: int, sku: string, name: string}>, by_name: array<string, array{id: int, sku: string, name: string}>}  $erpProductMaps
     * @return array{id: int, sku: string, name: string}|null
     */
    private function matchErpProduct(object $item, array $erpProductMaps): ?array
    {
        $skuKey = $this->normalizeSku((string) ($item->product_sku ?? ''));
        if ($skuKey !== '' && isset($erpProductMaps['by_sku'][$skuKey])) {
            return $erpProductMaps['by_sku'][$skuKey];
        }

        $nameKey = $this->normalizeCustomerName((string) ($item->name ?? $item->product_name ?? ''));
        if ($nameKey !== '' && isset($erpProductMaps['by_name'][$nameKey])) {
            return $erpProductMaps['by_name'][$nameKey];
        }

        return null;
    }

    /**
     * @param  array{by_email: array<string, array{id: int, name: string, email: string}>, by_name: array<string, array{id: int, name: string, email: string}>}  $erpUserMaps
     * @return array{id: int, name: string, email: string}|null
     */
    private function matchErpUser(string $legacyEmail, string $legacyName, array $erpUserMaps): ?array
    {
        $emailKey = mb_strtolower(trim($legacyEmail));
        if ($emailKey !== '' && isset($erpUserMaps['by_email'][$emailKey])) {
            return $erpUserMaps['by_email'][$emailKey];
        }

        $nameKey = $this->normalizeCustomerName($legacyName);
        if ($nameKey !== '' && isset($erpUserMaps['by_name'][$nameKey])) {
            return $erpUserMaps['by_name'][$nameKey];
        }

        return null;
    }

    private function normalizeSku(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function issueRow(string $severity, object $project, string $message): array
    {
        $catalog = self::ISSUE_CATALOG[$message] ?? [
            'code' => 'uncategorized',
            'label' => $message,
            'fix_hint' => 'Periksa detail project ini secara manual sebelum import.',
        ];

        return [
            'severity' => $severity,
            'code' => $catalog['code'],
            'label' => $catalog['label'],
            'project_number' => (string) ($project->project_number ?? '-'),
            'title' => (string) ($project->title ?? ''),
            'message' => $message,
            'fix_hint' => $catalog['fix_hint'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function buildIssueGroups(array $issues): array
    {
        return collect($issues)
            ->groupBy(fn (array $issue) => $issue['severity'].'|'.$issue['code'].'|'.$issue['message'])
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'severity' => $first['severity'],
                    'code' => $first['code'],
                    'label' => $first['label'],
                    'message' => $first['message'],
                    'fix_hint' => $first['fix_hint'],
                    'count' => $group->count(),
                    'project_numbers' => $group->pluck('project_number')->unique()->values()->all(),
                ];
            })
            ->sortBy([
                fn (array $item) => $item['severity'] === 'error' ? 0 : 1,
                fn (array $item) => -1 * $item['count'],
                fn (array $item) => $item['label'],
            ])
            ->values()
            ->all();
    }
}
