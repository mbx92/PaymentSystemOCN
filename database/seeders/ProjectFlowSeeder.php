<?php

namespace Database\Seeders;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use App\Models\ProjectTask;
use App\Models\Referral;
use App\Models\TeamDistribution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProjectFlowSeeder extends Seeder
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = $this->adminUser();
            $team = $this->teamUsers();
            $cashAccount = $this->cashAccount();
            $paymentMethod = $this->paymentMethod();
            $warehouse = $this->projectWarehouse();
            $material = $this->projectMaterialProduct();

            $this->ensureMaterialStock($material, $warehouse);

            foreach ($this->projectRows() as $index => $row) {
                $project = $this->upsertProject($row, $admin);
                $this->upsertBudget($row, $project);
                $payments = $this->upsertPayments($project, $row, $admin);
                $this->syncPaidTerms($payments, $row['paid_terms'], $admin, $cashAccount, $paymentMethod);
                $this->syncTeam($project, $team, $row);
                $this->syncTasks($project, $team, $row);
                $this->syncReferral($project, $row);
                $this->syncExpenses($project, $row, $admin, $cashAccount);
                $this->syncMaterial($project, $material, $warehouse, $row, $index);
            }
        });
    }

    private function adminUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'admin@ocn.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );
    }

    /**
     * @return array<string, User>
     */
    private function teamUsers(): array
    {
        $rows = [
            'lead' => ['name' => 'Rani Project Lead', 'email' => 'rani.lead@ocn.test'],
            'developer' => ['name' => 'Dimas Developer', 'email' => 'dimas.dev@ocn.test'],
            'designer' => ['name' => 'Maya Designer', 'email' => 'maya.design@ocn.test'],
            'qa' => ['name' => 'Tio QA', 'email' => 'tio.qa@ocn.test'],
        ];

        $users = [];
        foreach ($rows as $role => $row) {
            $users[$role] = User::query()->firstOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => Hash::make('password')]
            );
        }

        return $users;
    }

    private function cashAccount(): Account
    {
        return Account::query()->firstOrCreate(
            ['code' => '1001'],
            [
                'name' => 'Kas',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_active' => true,
            ]
        );
    }

    private function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::query()->updateOrCreate(
            ['code' => 'transfer'],
            ['name' => 'Transfer Bank', 'description' => 'Transfer ke rekening perusahaan', 'status' => 'active']
        );
    }

    private function projectWarehouse(): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            ['code' => 'CCTV'],
            ['name' => 'Warehouse CCTV', 'address' => 'Gudang material project', 'is_active' => true]
        );
    }

    private function projectMaterialProduct(): MasterProduct
    {
        return MasterProduct::query()->firstOrCreate(
            ['sku' => 'CCTV-UTP-CAT6'],
            [
                'barcode' => '899200110001',
                'name' => 'Kabel UTP Cat6',
                'category' => 'Material CCTV',
                'uom' => 'roll',
                'sales_channel' => 'project',
                'product_type' => 'project_material',
                'status' => 'active',
                'selling_price' => 775000,
                'stock' => 42,
                'min_stock' => 20,
                'total_sold' => 180,
                'lead_time_days' => 14,
            ]
        );
    }

    private function ensureMaterialStock(MasterProduct $material, Warehouse $warehouse): void
    {
        MasterProductWarehouseStock::query()->firstOrCreate(
            ['master_product_id' => $material->id, 'warehouse_id' => $warehouse->id],
            ['qty' => 42, 'reserved_qty' => 0]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(): array
    {
        return [
            [
                'key' => 'project-flow-001',
                'name' => 'Website Company Profile Klinik Aruna',
                'client_name' => 'Klinik Aruna',
                'client_contact' => '0812-4100-0001',
                'project_type' => 'system_website_development',
                'total_value' => 18000000,
                'status' => 'negosiasi',
                'started_at' => null,
                'finished_at' => null,
                'description' => 'Flow awal: budget sudah deal dan project masuk negosiasi.',
                'paid_terms' => 0,
                'task_statuses' => ['todo', 'todo', 'todo'],
                'material' => null,
                'expenses' => [],
                'referral_paid' => false,
            ],
            [
                'key' => 'project-flow-002',
                'name' => 'Sistem Inventory CV Sinar Maju',
                'client_name' => 'CV Sinar Maju',
                'client_contact' => '0812-4100-0002',
                'project_type' => 'system_website_development',
                'total_value' => 42000000,
                'status' => 'berjalan',
                'started_at' => now()->subDays(24)->toDateString(),
                'finished_at' => null,
                'description' => 'Flow berjalan: DP sudah masuk, pekerjaan aktif, dan biaya operasional mulai tercatat.',
                'paid_terms' => 1,
                'task_statuses' => ['done', 'in_progress', 'todo'],
                'material' => null,
                'expenses' => [
                    ['category' => 'operasional', 'amount' => 2500000, 'recipient_name' => 'Tim Operasional', 'note' => 'Transportasi dan meeting kick-off'],
                ],
                'referral_paid' => true,
            ],
            [
                'key' => 'project-flow-003',
                'name' => 'Instalasi CCTV Gudang Makmur',
                'client_name' => 'PT Gudang Makmur',
                'client_contact' => '0812-4100-0003',
                'project_type' => 'cctv_installation',
                'total_value' => 36500000,
                'status' => 'berjalan',
                'started_at' => now()->subDays(16)->toDateString(),
                'finished_at' => null,
                'description' => 'Flow CCTV: DP masuk, kebutuhan material di-reserve, dan pembelian material dicatat.',
                'paid_terms' => 1,
                'task_statuses' => [],
                'material' => ['planned_qty' => 8, 'reserved_qty' => 6, 'issued_qty' => 2, 'status' => 'partial'],
                'expenses' => [
                    ['category' => 'pembelian_material_project', 'amount' => 6200000, 'recipient_name' => 'Supplier CCTV', 'note' => 'Pembelian kabel dan aksesoris instalasi'],
                ],
                'referral_paid' => false,
            ],
            [
                'key' => 'project-flow-004',
                'name' => 'Aplikasi Booking Salon Lestari',
                'client_name' => 'Salon Lestari',
                'client_contact' => '0812-4100-0004',
                'project_type' => 'system_website_development',
                'total_value' => 28500000,
                'status' => 'selesai',
                'started_at' => now()->subDays(55)->toDateString(),
                'finished_at' => now()->subDays(5)->toDateString(),
                'description' => 'Flow selesai: semua termin lunas, tim dibayar, dan invoice sudah tersedia.',
                'paid_terms' => 3,
                'task_statuses' => ['done', 'done', 'done'],
                'material' => null,
                'expenses' => [
                    ['category' => 'biaya_tim', 'amount' => 9500000, 'recipient_name' => 'Tim Project', 'note' => 'Pembayaran final tim project'],
                    ['category' => 'komisi_referral', 'amount' => 1500000, 'recipient_name' => 'Mitra Referral', 'note' => 'Komisi referral project selesai'],
                ],
                'referral_paid' => true,
            ],
            [
                'key' => 'project-flow-005',
                'name' => 'Portal Membership Komunitas Nusa',
                'client_name' => 'Komunitas Nusa',
                'client_contact' => '0812-4100-0005',
                'project_type' => 'system_website_development',
                'total_value' => 24000000,
                'status' => 'dibatalkan',
                'started_at' => null,
                'finished_at' => null,
                'description' => 'Flow batal: budget sempat masuk, tetapi project dibatalkan sebelum pembayaran.',
                'paid_terms' => 0,
                'task_statuses' => ['todo', 'todo', 'todo'],
                'material' => null,
                'expenses' => [],
                'referral_paid' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertProject(array $row, User $admin): Project
    {
        $status = $row['status'] === 'negosiasi'
            ? DocumentStatus::Submitted
            : DocumentStatus::Posted;

        return Project::query()->updateOrCreate(
            ['import_key' => $row['key']],
            [
                'name' => $row['name'],
                'client_name' => $row['client_name'],
                'client_contact' => $row['client_contact'],
                'project_type' => $row['project_type'],
                'total_value' => $row['total_value'],
                'status' => $row['status'],
                'invoice_number' => $row['status'] === 'selesai' ? 'INV-PRJ-SEED-004' : null,
                'invoiced_at' => $row['status'] === 'selesai' ? now()->subDays(5) : null,
                'document_status' => $status->value,
                'approved_at' => $row['status'] === 'negosiasi' ? null : now()->subDays(20),
                'approved_by' => $row['status'] === 'negosiasi' ? null : $admin->id,
                'posted_at' => $row['status'] === 'negosiasi' ? null : now()->subDays(19),
                'posted_by' => $row['status'] === 'negosiasi' ? null : $admin->id,
                'started_at' => $row['started_at'],
                'finished_at' => $row['finished_at'],
                'description' => $row['description'],
                'legal_vault_path' => 'Project Contracts/'.str($row['name'])->slug(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertBudget(array $row, Project $project): void
    {
        ProjectBudget::query()->updateOrCreate(
            ['name' => $row['name'], 'client_name' => $row['client_name']],
            [
                'client_contact' => $row['client_contact'],
                'project_type' => $row['project_type'],
                'estimated_value' => $row['total_value'],
                'cctv_items' => $row['project_type'] === 'cctv_installation'
                    ? [
                        ['name' => 'Paket kamera CCTV 8 titik', 'qty' => 1, 'unit_price' => 24500000],
                        ['name' => 'Kabel dan instalasi', 'qty' => 1, 'unit_price' => 12000000],
                    ]
                    : [],
                'description' => 'Budget awal untuk '.$row['name'],
                'status' => 'converted',
                'deal_at' => now()->subDays(30),
                'converted_project_id' => $project->id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Collection<int, ProjectPayment>
     */
    private function upsertPayments(Project $project, array $row, User $admin)
    {
        $percentages = [40, 30, 30];
        $notes = ['Uang muka project', 'Termin progress', 'Pelunasan final'];
        $payments = collect();

        foreach ($percentages as $index => $percentage) {
            $termNumber = $index + 1;
            $amount = $termNumber === 3
                ? (float) $row['total_value'] - round((float) $row['total_value'] * 0.7, 2)
                : round((float) $row['total_value'] * ($percentage / 100), 2);

            $paidAt = $termNumber <= (int) $row['paid_terms']
                ? now()->subDays(18 - ($index * 6))->toDateString()
                : null;

            $payments->push(ProjectPayment::query()->updateOrCreate(
                ['project_id' => $project->id, 'term_number' => $termNumber],
                [
                    'percentage' => $percentage,
                    'amount' => $amount,
                    'document_status' => $paidAt ? DocumentStatus::Posted->value : DocumentStatus::Approved->value,
                    'approved_at' => now()->subDays(24),
                    'approved_by' => $admin->id,
                    'posted_at' => $paidAt ? now()->subDays(20) : null,
                    'posted_by' => $paidAt ? $admin->id : null,
                    'paid_at' => $paidAt,
                    'note' => $notes[$index],
                ]
            ));
        }

        return $payments;
    }

    private function syncPaidTerms($payments, int $paidTerms, User $admin, Account $cashAccount, PaymentMethod $paymentMethod): void
    {
        foreach ($payments as $payment) {
            if ((int) $payment->term_number > $paidTerms) {
                CashIn::query()->where('project_payment_id', $payment->id)->delete();

                continue;
            }

            $cashIn = CashIn::query()->updateOrCreate(
                ['project_payment_id' => $payment->id],
                [
                    'project_id' => $payment->project_id,
                    'payment_method_id' => $paymentMethod->id,
                    'cash_account_id' => $cashAccount->id,
                    'category' => $payment->term_number === 1 ? 'uang_muka_project' : 'pendapatan_project',
                    'amount' => $payment->amount,
                    'document_status' => DocumentStatus::Posted->value,
                    'approved_at' => now()->subDays(18),
                    'approved_by' => $admin->id,
                    'posted_at' => now()->subDays(17),
                    'posted_by' => $admin->id,
                    'date' => $payment->paid_at ?? now()->toDateString(),
                    'note' => 'Pembayaran '.$payment->note,
                    'created_by' => $admin->id,
                ]
            );

            $this->postCashInJournal($cashIn, $cashAccount);
        }
    }

    /**
     * @param  array<string, User>  $team
     * @param  array<string, mixed>  $row
     */
    private function syncTeam(Project $project, array $team, array $row): void
    {
        $netTeamBudget = (float) $row['total_value'] * 0.35;
        $rows = [
            ['role' => 'lead', 'percentage' => 30, 'bonus' => 500000],
            ['role' => 'developer', 'percentage' => 40, 'bonus' => 750000],
            ['role' => 'designer', 'percentage' => 20, 'bonus' => 350000],
            ['role' => 'qa', 'percentage' => 10, 'bonus' => 250000],
        ];

        foreach ($rows as $item) {
            $basePay = round($netTeamBudget * ($item['percentage'] / 100), 2);

            TeamDistribution::query()->updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $team[$item['role']]->id],
                [
                    'role_in_project' => $item['role'],
                    'percentage' => $item['percentage'],
                    'base_pay' => $basePay,
                    'bonus' => $item['bonus'],
                    'total_pay' => $basePay + $item['bonus'],
                ]
            );
        }
    }

    /**
     * @param  array<string, User>  $team
     * @param  array<string, mixed>  $row
     */
    private function syncTasks(Project $project, array $team, array $row): void
    {
        if ($row['project_type'] !== 'system_website_development') {
            ProjectTask::query()->where('project_id', $project->id)->delete();

            return;
        }

        $tasks = [
            ['title' => 'Discovery kebutuhan dan scope', 'assignee' => 'lead'],
            ['title' => 'Implementasi modul utama', 'assignee' => 'developer'],
            ['title' => 'QA, revisi, dan handover', 'assignee' => 'qa'],
        ];

        foreach ($tasks as $index => $task) {
            ProjectTask::query()->updateOrCreate(
                ['project_id' => $project->id, 'title' => $task['title']],
                [
                    'description' => 'Task seeder untuk alur project '.$project->name,
                    'status' => $row['task_statuses'][$index] ?? 'todo',
                    'assigned_user_id' => $team[$task['assignee']]->id,
                    'due_date' => now()->addDays(($index + 1) * 7)->toDateString(),
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncReferral(Project $project, array $row): void
    {
        $commission = round((float) $row['total_value'] * 0.05, 2);

        Referral::query()->updateOrCreate(
            ['project_id' => $project->id, 'referrer_name' => 'Mitra Referral OCN'],
            [
                'commission_amount' => $commission,
                'paid_at' => $row['referral_paid'] ? now()->subDays(4)->toDateString() : null,
                'note' => 'Komisi referral 5% dari nilai project.',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncExpenses(Project $project, array $row, User $admin, Account $cashAccount): void
    {
        foreach ($row['expenses'] as $expense) {
            $cashOut = CashOut::query()->updateOrCreate(
                ['project_id' => $project->id, 'category' => $expense['category'], 'note' => $expense['note']],
                [
                    'cash_account_id' => $cashAccount->id,
                    'amount' => $expense['amount'],
                    'document_status' => DocumentStatus::Posted->value,
                    'approved_at' => now()->subDays(12),
                    'approved_by' => $admin->id,
                    'posted_at' => now()->subDays(11),
                    'posted_by' => $admin->id,
                    'date' => now()->subDays(10)->toDateString(),
                    'recipient_name' => $expense['recipient_name'],
                    'created_by' => $admin->id,
                ]
            );

            $this->postCashOutJournal($cashOut, $cashAccount);
        }
    }

    private function postCashInJournal(CashIn $cashIn, Account $cashAccount): void
    {
        $creditAccount = $this->mappedAccount('cash_in', $cashIn->category, '4003');
        $this->upsertJournal(
            cashflow: $cashIn,
            sourceModule: 'cash_in',
            description: 'Seeder kas masuk project '.$cashIn->project_id,
            lines: [
                ['account_id' => $cashAccount->id, 'debit' => $cashIn->amount, 'credit' => 0],
                ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $cashIn->amount],
            ],
        );
    }

    private function postCashOutJournal(CashOut $cashOut, Account $cashAccount): void
    {
        $debitAccount = $this->mappedAccount('cash_out', $cashOut->category, '5001');
        $this->upsertJournal(
            cashflow: $cashOut,
            sourceModule: 'cash_out',
            description: 'Seeder kas keluar project '.$cashOut->project_id,
            lines: [
                ['account_id' => $debitAccount->id, 'debit' => $cashOut->amount, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $cashOut->amount],
            ],
        );
    }

    /**
     * @param  array<int, array{account_id:int, debit:mixed, credit:mixed}>  $lines
     */
    private function upsertJournal(CashIn|CashOut $cashflow, string $sourceModule, string $description, array $lines): void
    {
        $entry = $cashflow->journal_entry_id
            ? JournalEntry::query()->with('lines')->find($cashflow->journal_entry_id)
            : null;

        $entry ??= JournalEntry::query()
            ->with('lines')
            ->where('source_module', $sourceModule)
            ->where('source_reference', (string) $cashflow->id)
            ->first();

        if (! $entry) {
            $entry = $this->glPostingService->post(
                sourceModule: $sourceModule,
                sourceReference: (string) $cashflow->id,
                description: $description,
                entryDate: $cashflow->date->toDateString(),
                lines: $lines,
            );

            $cashflow->update(['journal_entry_id' => $entry->id]);

            return;
        }

        $entry->update([
            'entry_date' => $cashflow->date,
            'description' => $description,
            'status' => DocumentStatus::Posted,
            'source_module' => $sourceModule,
            'source_reference' => (string) $cashflow->id,
            'posted_at' => $cashflow->posted_at ?? now(),
            'posted_by' => $cashflow->posted_by,
        ]);

        $entry->lines()->delete();
        foreach ($lines as $line) {
            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }

        if ((int) $cashflow->journal_entry_id !== (int) $entry->id) {
            $cashflow->update(['journal_entry_id' => $entry->id]);
        }
    }

    private function mappedAccount(string $domain, string $category, string $fallbackCode): Account
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', $domain)
            ->where('category', $category)
            ->value('account_id');

        if ($accountId) {
            $account = Account::query()->find($accountId);
            if ($account) {
                return $account;
            }
        }

        return Account::query()->where('code', $fallbackCode)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncMaterial(Project $project, MasterProduct $material, Warehouse $warehouse, array $row, int $index): void
    {
        if (! $row['material']) {
            ProjectMaterial::query()
                ->where('project_id', $project->id)
                ->where('master_product_id', $material->id)
                ->where('warehouse_id', $warehouse->id)
                ->delete();

            return;
        }

        ProjectMaterial::query()->updateOrCreate(
            ['project_id' => $project->id, 'master_product_id' => $material->id, 'warehouse_id' => $warehouse->id],
            [
                'planned_qty' => $row['material']['planned_qty'],
                'reserved_qty' => $row['material']['reserved_qty'],
                'issued_qty' => $row['material']['issued_qty'],
                'status' => $row['material']['status'],
                'notes' => 'Material seed untuk flow CCTV project.',
            ]
        );

        $reservedQty = (float) ProjectMaterial::query()
            ->where('master_product_id', $material->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereHas('project', fn ($query) => $query->where('import_key', 'like', 'project-flow-%'))
            ->sum('reserved_qty');

        MasterProductWarehouseStock::query()->updateOrCreate(
            ['master_product_id' => $material->id, 'warehouse_id' => $warehouse->id],
            ['qty' => max(42, $reservedQty + 10 + $index), 'reserved_qty' => $reservedQty]
        );
    }
}
