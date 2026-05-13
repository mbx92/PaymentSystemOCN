<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\CashCategory;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use App\Models\ProjectTask;
use App\Models\TeamDistribution;
use App\Models\TeamRole;
use App\Models\User;
use App\Support\LegalVaultPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with(['payments'])
            ->withSum('cashIns as paid_amount', 'amount')
            ->when($request->search, fn ($q) => $q->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('client_name', 'ilike', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->project_type, fn ($q) => $q->where('project_type', $request->project_type));

        $projects = $query->latest()->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'client_name' => $p->client_name,
                'project_type' => $p->project_type,
                'status' => $p->status,
                'total_value' => (float) $p->total_value,
                'paid_amount' => (float) ($p->paid_amount ?? 0),
                'started_at' => $p->started_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $this->filtersWithPerPage($request, ['search', 'status', 'project_type']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Projects/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => 'nullable|in:cctv_installation,system_website_development',
            'total_value' => 'required|numeric|min:0.01',
            'status' => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'payment_scheme' => 'nullable|in:terms,final',
            'payments' => 'required|array|min:1|max:20',
            'payments.*.percentage' => 'required|numeric|min:0.01|max:100',
            'payments.*.note' => 'nullable|string|max:500',
        ]);

        if (($validated['payment_scheme'] ?? 'terms') === 'final') {
            $validated['payments'] = [[
                'percentage' => 100,
                'note' => 'Pelunasan di akhir',
            ]];
        }

        $this->assertPaymentsTotalHundredPercent($validated['payments']);
        $validated['project_type'] = $validated['project_type'] ?? 'system_website_development';

        DB::transaction(function () use ($validated) {
            $project = Project::create(collect($validated)->except('payments')->all());
            $this->createPaymentRows($project, $validated['payments']);
        });

        return redirect()->route('projects.index')->with('flash', ['type' => 'success', 'message' => 'Project berhasil ditambahkan.']);
    }

    public function show(Project $project)
    {
        $project->load(['payments', 'cashIns.creator', 'cashOuts.creator', 'teamDistributions.user', 'referrals', 'tasks.assignee']);
        $project->load(['materials.product', 'materials.warehouse']);
        if (! TeamRole::query()->exists()) {
            foreach (['Lead', 'Developer', 'Designer', 'QA'] as $name) {
                TeamRole::query()->create([
                    'name' => $name,
                    'is_active' => true,
                ]);
            }
        }
        $teamRoles = TeamRole::query()->where('is_active', true)->orderBy('name')->get();

        $legalVaultRelativePath = $this->existingLegalVaultRelativePath($project);
        $legalVaultFolderExists = $legalVaultRelativePath !== null && $this->legalVaultPathExists($legalVaultRelativePath);
        $cashInCategories = $this->cashCategoryOptions('cash_in');
        $cashOutCategories = $this->cashCategoryOptions('cash_out');
        $cashCategoryLabels = $cashInCategories
            ->concat($cashOutCategories)
            ->pluck('label', 'value');
        $materialFundReceived = (float) $project->cashIns->where('category', 'dana_material_client')->sum('amount');
        $materialFundUsed = (float) $project->cashOuts->where('category', 'pemakaian_dana_material_client')->sum('amount');

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'client_contact' => $project->client_contact,
                'project_type' => $project->project_type,
                'total_value' => (float) $project->total_value,
                'status' => $project->status,
                'started_at' => $project->started_at?->format('Y-m-d'),
                'finished_at' => $project->finished_at?->format('Y-m-d'),
                'description' => $project->description,
                'project_type' => $project->project_type,
                'payments' => $project->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'term_number' => $p->term_number,
                    'percentage' => (float) $p->percentage,
                    'amount' => (float) $p->amount,
                    'paid_at' => $p->paid_at?->format('Y-m-d'),
                    'note' => $p->note,
                ]),
                'cash_ins' => $project->cashIns->map(fn ($c) => [
                    'id' => $c->id,
                    'category' => $c->category,
                    'amount' => (float) $c->amount,
                    'date' => $c->date->format('Y-m-d'),
                    'note' => $c->note,
                    'creator_name' => $c->creator->name,
                ]),
                'cash_outs' => $project->cashOuts->map(fn ($c) => [
                    'id' => $c->id,
                    'category' => $c->category,
                    'amount' => (float) $c->amount,
                    'date' => $c->date->format('Y-m-d'),
                    'note' => $c->note,
                    'recipient_name' => $c->recipient_name,
                    'creator_name' => $c->creator->name,
                ]),
                'team_distributions' => $project->teamDistributions->map(fn ($d) => [
                    'id' => $d->id,
                    'user_id' => $d->user_id,
                    'user_name' => $d->user->name,
                    'role_in_project' => $d->role_in_project,
                    'percentage' => (float) $d->percentage,
                    'base_pay' => (float) $d->base_pay,
                    'bonus' => (float) $d->bonus,
                    'total_pay' => (float) $d->total_pay,
                ]),
                'tasks' => $project->tasks->map(fn ($task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'assigned_user_id' => $task->assigned_user_id,
                    'assigned_user_name' => $task->assignee?->name,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                ]),
                'referrals' => $project->referrals->map(fn ($r) => [
                    'id' => $r->id,
                    'referrer_name' => $r->referrer_name,
                    'commission_amount' => (float) $r->commission_amount,
                    'paid_at' => $r->paid_at?->format('Y-m-d'),
                    'note' => $r->note,
                ]),
                'summary' => [
                    'total_cash_in' => $project->total_cash_in,
                    'total_cash_out' => $project->total_cash_out,
                    'profit' => $project->profit,
                    'total_referral_commission' => $project->total_referral_commission,
                    'total_operational' => $project->total_operational,
                    'net_team_value' => $project->net_team_value,
                    'material_fund_received' => $materialFundReceived,
                    'material_fund_used' => $materialFundUsed,
                    'material_fund_balance' => $materialFundReceived - $materialFundUsed,
                ],
                'materials' => $project->materials->map(fn ($m) => [
                    'id' => $m->id,
                    'product' => $m->product?->name,
                    'sku' => $m->product?->sku,
                    'uom' => $m->product?->uom,
                    'warehouse' => $m->warehouse?->name,
                    'planned_qty' => (float) $m->planned_qty,
                    'reserved_qty' => (float) $m->reserved_qty,
                    'issued_qty' => (float) $m->issued_qty,
                    'status' => $m->status,
                    'notes' => $m->notes,
                ]),
                'legal_documents' => [
                    'vault_path' => $legalVaultRelativePath,
                    'folder_exists' => $legalVaultFolderExists,
                    'has_saved_mapping' => $project->legal_vault_path !== null && trim((string) $project->legal_vault_path) !== '',
                    'uses_custom_mapping' => $project->legal_vault_path !== null && trim((string) $project->legal_vault_path) !== '',
                    'default_path_hint' => $this->defaultLegalVaultRelativePath($project),
                ],
                'invoice' => [
                    'available' => $project->status === 'selesai',
                    'number' => $project->invoice_number,
                    'show_url' => $project->status === 'selesai'
                        ? route('erp.sales.project-invoices.show', $project)
                        : null,
                ],
            ],
            'material_products' => MasterProduct::query()
                ->where('status', 'active')
                ->where('product_type', 'project_material')
                ->whereIn('sales_channel', ['project', 'both'])
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'uom', 'sales_channel', 'product_type']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouse_stocks' => MasterProductWarehouseStock::query()
                ->get(['master_product_id', 'warehouse_id', 'qty', 'reserved_qty'])
                ->groupBy('warehouse_id')
                ->map(fn ($rows) => $rows->keyBy('master_product_id')->map(fn ($r) => [
                    'qty' => (float) $r->qty,
                    'reserved' => (float) $r->reserved_qty,
                    'available' => max((float) $r->qty - (float) $r->reserved_qty, 0),
                ])),
            'team_members' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'team_roles' => $teamRoles->map(fn (TeamRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ]),
            'cash_accounts' => Account::query()
                ->where('is_active', true)
                ->where('type', 'asset')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'cash_category_options' => [
                'in' => $cashInCategories,
                'out' => $cashOutCategories,
                'labels' => $cashCategoryLabels,
            ],
        ]);
    }

    public function storeTeamMember(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'team_role_id' => 'required|integer|exists:team_roles,id',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'base_pay' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'total_pay' => 'nullable|numeric|min:0',
        ]);

        $role = TeamRole::query()->findOrFail((int) $validated['team_role_id']);
        $basePay = (float) ($validated['base_pay'] ?? 0);
        $bonus = (float) ($validated['bonus'] ?? 0);
        $computedTotalPay = $basePay + $bonus;
        $totalPay = array_key_exists('total_pay', $validated) ? (float) $validated['total_pay'] : $computedTotalPay;

        TeamDistribution::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => (int) $validated['user_id'],
            ],
            [
                'role_in_project' => $role->name,
                'percentage' => (float) ($validated['percentage'] ?? 0),
                'base_pay' => $basePay,
                'bonus' => $bonus,
                'total_pay' => $totalPay,
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Anggota tim berhasil di-assign.']);
    }

    public function destroyTeamMember(Project $project, TeamDistribution $teamDistribution)
    {
        if ($teamDistribution->project_id !== $project->id) {
            abort(404);
        }

        $teamDistribution->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Anggota tim berhasil dilepas dari project.']);
    }

    public function storeTask(Request $request, Project $project)
    {
        if ($project->project_type !== 'system_website_development') {
            throw ValidationException::withMessages([
                'project' => 'Kanban task hanya tersedia untuk project System/Website Development.',
            ]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:todo,in_progress,done',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $nextSortOrder = (int) ProjectTask::query()
            ->where('project_id', $project->id)
            ->max('sort_order') + 1;

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'todo',
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'sort_order' => $nextSortOrder,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Task berhasil ditambahkan.']);
    }

    public function updateTask(Request $request, Project $project, ProjectTask $task)
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:todo,in_progress,done',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Task berhasil diperbarui.']);
    }

    public function destroyTask(Project $project, ProjectTask $task)
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }

        $task->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Task berhasil dihapus.']);
    }

    public function edit(Project $project)
    {
        $project->load('payments');

        $canEditPayments = ! $project->payments()->whereNotNull('paid_at')->exists();

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'client_contact' => $project->client_contact,
                'project_type' => $project->project_type,
                'total_value' => (float) $project->total_value,
                'status' => $project->status,
                'started_at' => $project->started_at?->format('Y-m-d') ?? '',
                'finished_at' => $project->finished_at?->format('Y-m-d') ?? '',
                'description' => $project->description ?? '',
                'legal_vault_path' => $project->legal_vault_path ?? '',
                'suggested_legal_vault_path' => $this->defaultLegalVaultRelativePath($project),
            ],
            'payments' => $project->payments->map(fn ($p) => [
                'id' => $p->id,
                'term_number' => $p->term_number,
                'percentage' => (float) $p->percentage,
                'amount' => (float) $p->amount,
                'note' => $p->note ?? '',
                'paid_at' => $p->paid_at?->format('Y-m-d'),
            ]),
            'can_edit_payments' => $canEditPayments,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $canEditPayments = ! $project->payments()->whereNotNull('paid_at')->exists();

        if ($request->has('payments') && ! $canEditPayments) {
            throw ValidationException::withMessages([
                'payments' => 'Jadwal termin tidak dapat diubah karena sudah ada pembayaran yang ditandai lunas.',
            ]);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => 'nullable|in:cctv_installation,system_website_development',
            'total_value' => 'required|numeric|min:0.01',
            'status' => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'legal_vault_path' => 'nullable|string|max:2000',
        ];

        if ($canEditPayments) {
            $rules['payments'] = 'required|array|min:1|max:20';
            $rules['payments.*.percentage'] = 'required|numeric|min:0.01|max:100';
            $rules['payments.*.note'] = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);
        $validated['project_type'] = $validated['project_type'] ?? $project->project_type ?? 'system_website_development';

        $legalRaw = $request->input('legal_vault_path');
        if ($legalRaw === null || (is_string($legalRaw) && trim($legalRaw) === '')) {
            $validated['legal_vault_path'] = null;
        } else {
            try {
                $validated['legal_vault_path'] = LegalVaultPath::normalize(trim((string) $legalRaw));
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'legal_vault_path' => $e->getMessage(),
                ]);
            }
        }

        if ($canEditPayments) {
            $this->assertPaymentsTotalHundredPercent($validated['payments']);
        }

        DB::transaction(function () use ($project, $validated, $canEditPayments) {
            $project->update(collect($validated)->except('payments')->all());

            if ($canEditPayments) {
                $this->replacePaymentSchedule($project, $validated['payments']);
            }
        });

        return redirect()->route('projects.show', $project)->with('flash', ['type' => 'success', 'message' => 'Project berhasil diperbarui.']);
    }

    public function updateStatus(Request $request, Project $project)
    {
        $validated = $request->validate([
            'target_status' => 'required|in:berjalan,selesai',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date',
        ]);

        $target = $validated['target_status'];

        if ($target === 'berjalan') {
            if ($project->status !== 'negosiasi') {
                throw ValidationException::withMessages([
                    'target_status' => 'Hanya project berstatus negosiasi yang bisa diubah menjadi berjalan.',
                ]);
            }

            if (empty($validated['started_at'])) {
                throw ValidationException::withMessages([
                    'started_at' => 'Tanggal mulai wajib diisi saat mengubah status ke berjalan.',
                ]);
            }

            $project->update([
                'status' => 'berjalan',
                'started_at' => $validated['started_at'],
            ]);

            return back()->with('flash', ['type' => 'success', 'message' => 'Status project diubah ke berjalan.']);
        }

        if ($project->status !== 'berjalan') {
            throw ValidationException::withMessages([
                'target_status' => 'Hanya project berstatus berjalan yang bisa diubah menjadi selesai.',
            ]);
        }

        if (empty($validated['finished_at'])) {
            throw ValidationException::withMessages([
                'finished_at' => 'Tanggal selesai wajib diisi saat mengubah status ke selesai.',
            ]);
        }

        if (! empty($project->started_at) && $validated['finished_at'] < $project->started_at->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'finished_at' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai project.',
            ]);
        }

        $project->update([
            'status' => 'selesai',
            'finished_at' => $validated['finished_at'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Status project diubah ke selesai.']);
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('flash', ['type' => 'success', 'message' => 'Project berhasil dihapus.']);
    }

    public function storeMaterial(Request $request, Project $project)
    {
        $validated = $request->validate([
            'master_product_id' => [
                'required',
                Rule::exists('master_products', 'id')
                    ->where('status', 'active')
                    ->where('product_type', 'project_material')
                    ->whereIn('sales_channel', ['project', 'both']),
            ],
            'warehouse_id' => 'required|exists:warehouses,id',
            'planned_qty' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($project, $validated): void {
            $stock = MasterProductWarehouseStock::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'master_product_id' => (int) $validated['master_product_id'],
                        'warehouse_id' => (int) $validated['warehouse_id'],
                    ],
                    ['qty' => 0, 'reserved_qty' => 0],
                );

            $available = max((float) $stock->qty - (float) $stock->reserved_qty, 0);
            $plannedQty = (float) $validated['planned_qty'];
            $toReserve = min($plannedQty, $available);

            $material = ProjectMaterial::query()->firstOrNew([
                'project_id' => $project->id,
                'master_product_id' => (int) $validated['master_product_id'],
                'warehouse_id' => (int) $validated['warehouse_id'],
            ]);

            if (! $material->exists) {
                $material->planned_qty = 0;
                $material->reserved_qty = 0;
                $material->issued_qty = 0;
                $material->status = 'reserved';
            }

            $material->planned_qty = (float) $material->planned_qty + $plannedQty;
            $material->reserved_qty = (float) $material->reserved_qty + $toReserve;
            $material->notes = $validated['notes'] ?? $material->notes;
            $material->status = $this->projectMaterialStatus($material);
            $material->save();

            if ($toReserve > 0) {
                $stock->increment('reserved_qty', $toReserve);
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kebutuhan material project berhasil ditambahkan. Stok tersedia otomatis di-reserve, kekurangan masuk perencanaan PO.']);
    }

    public function destroyMaterial(Project $project, ProjectMaterial $material)
    {
        if ($material->project_id !== $project->id) {
            abort(404);
        }

        DB::transaction(function () use ($material): void {
            $toRelease = max((float) $material->reserved_qty - (float) $material->issued_qty, 0);
            if ($toRelease > 0) {
                $stock = MasterProductWarehouseStock::query()
                    ->lockForUpdate()
                    ->where('master_product_id', $material->master_product_id)
                    ->where('warehouse_id', $material->warehouse_id)
                    ->first();
                if ($stock) {
                    $newReserved = max((float) $stock->reserved_qty - $toRelease, 0);
                    $stock->update(['reserved_qty' => $newReserved]);
                }
            }

            $material->delete();
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Material project dihapus dan reserve dikembalikan.']);
    }

    public function createLegalFolder(Project $project)
    {
        $relative = $project->legal_vault_path !== null && trim((string) $project->legal_vault_path) !== ''
            ? trim((string) $project->legal_vault_path)
            : $this->defaultLegalVaultRelativePath($project);

        try {
            $relative = LegalVaultPath::normalize($relative);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'legal_vault_path' => $e->getMessage(),
            ]);
        }

        $this->mkdirLegalVaultPath($relative);

        if ($project->legal_vault_path !== $relative) {
            $project->update(['legal_vault_path' => $relative]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Folder dokumen project berhasil dibuat.']);
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

    private function cashCategoryOptions(string $domain)
    {
        return CashCategory::query()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label'])
            ->map(fn (CashCategory $category) => [
                'value' => $category->key,
                'label' => $category->label,
            ])
            ->values();
    }

    protected function assertPaymentsTotalHundredPercent(array $payments): void
    {
        $totalPct = collect($payments)->sum('percentage');
        if (abs($totalPct - 100) > 0.02) {
            throw ValidationException::withMessages([
                'payments' => 'Total persentase termin harus tepat 100% (saat ini: '.round($totalPct, 2).'%).',
            ]);
        }
    }

    protected function createPaymentRows(Project $project, array $payments): void
    {
        $totalValue = (float) $project->total_value;
        $n = count($payments);
        $assigned = 0.0;

        foreach ($payments as $i => $term) {
            $pct = (float) $term['percentage'];
            if ($i === $n - 1) {
                $amount = round($totalValue - $assigned, 2);
            } else {
                $amount = round($totalValue * ($pct / 100), 2);
                $assigned += $amount;
            }

            ProjectPayment::create([
                'project_id' => $project->id,
                'term_number' => $i + 1,
                'percentage' => $pct,
                'amount' => $amount,
                'note' => $term['note'] ?? null,
            ]);
        }
    }

    protected function replacePaymentSchedule(Project $project, array $payments): void
    {
        $project->payments()->delete();
        $this->createPaymentRows($project, $payments);
    }

    private function existingLegalVaultRelativePath(Project $project): ?string
    {
        $custom = $project->legal_vault_path;
        if (is_string($custom) && trim($custom) !== '') {
            try {
                $normalized = LegalVaultPath::normalize(trim($custom));
            } catch (\InvalidArgumentException) {
                return null;
            }
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $default = $this->defaultLegalVaultRelativePath($project);

        return $this->legalVaultPathExists($default) ? $default : null;
    }

    /**
     * Folder default: Project Contracts / {slug nama project}.
     */
    private function defaultLegalVaultRelativePath(Project $project): string
    {
        $slug = Str::slug($project->name);
        if ($slug === '') {
            $slug = 'project-'.strtolower(str_replace('-', '', substr((string) $project->getKey(), 0, 8)));
        }

        return 'Project Contracts/'.$slug;
    }

    private function mkdirLegalVaultPath(string $relative): void
    {
        $root = storage_path('app/legal-vault');
        if (! File::isDirectory($root)) {
            File::makeDirectory($root, 0755, true);
        }

        $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (! File::isDirectory($target)) {
            File::makeDirectory($target, 0755, true);
        }
    }

    private function legalVaultPathExists(string $relative): bool
    {
        $root = storage_path('app/legal-vault');
        $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return File::isDirectory($target);
    }
}
