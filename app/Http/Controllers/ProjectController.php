<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\CashCategory;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use App\Models\ProjectTask;
use App\Models\ProjectType;
use App\Services\ProjectMaterialReservationService;
use App\Models\TeamDistribution;
use App\Models\TeamRole;
use App\Models\User;
use App\Support\LegalVaultPath;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $query = Project::with(['materials', 'convertedBudget.items', 'projectTypeDefinition'])
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
                'project_type_label' => $p->projectTypeDefinition?->label ?: (string) $p->project_type,
                'supports_budget_items' => (bool) $p->projectTypeDefinition?->supports_budget_items,
                'supports_project_board' => (bool) $p->projectTypeDefinition?->supports_project_board,
                'status' => $p->status,
                'total_value' => $p->resolveListTotalValue(),
                'paid_amount' => (float) ($p->paid_amount ?? 0),
                'started_at' => $p->started_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $this->filtersWithPerPage($request, ['search', 'status', 'project_type']),
            'crm_customers' => $this->crmCustomerOptions(),
            'project_types' => $this->projectTypeOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Projects/Create', [
            'crm_customers' => $this->crmCustomerOptions(),
            'project_types' => $this->projectTypeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crm_customer_id' => 'required|exists:crm_customers,id',
            'project_type' => [
                'nullable',
                Rule::exists('project_types', 'key')->where('is_active', true),
            ],
            'total_value' => 'nullable|numeric|min:0',
            'status' => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'payment_scheme' => 'nullable|in:terms,final',
            'payments' => 'nullable|array|min:1|max:20',
            'payments.*.percentage' => 'required|numeric|min:0.01|max:100',
            'payments.*.note' => 'nullable|string|max:500',
        ]);

        $validated['total_value'] = (float) ($validated['total_value'] ?? 0);
        if ($validated['total_value'] > 0) {
            if (($validated['payment_scheme'] ?? 'terms') === 'final') {
                $validated['payments'] = [[
                    'percentage' => 100,
                    'note' => 'Pelunasan di akhir',
                ]];
            }

            if (empty($validated['payments'])) {
                throw ValidationException::withMessages([
                    'payments' => 'Jadwal termin wajib diisi jika project memiliki nilai kontrak.',
                ]);
            }

            $this->assertPaymentsTotalHundredPercent($validated['payments']);
        } else {
            $validated['payments'] = [];
        }
        $validated['project_type'] = $validated['project_type'] ?? ProjectType::defaultKey();
        $validated = array_merge($validated, $this->projectClientSnapshot((int) $validated['crm_customer_id']));

        DB::transaction(function () use ($validated) {
            $project = Project::create(Arr::except($validated, ['payments']));
            if (! empty($validated['payments'])) {
                $this->createPaymentRows($project, $validated['payments']);
            }
        });

        return redirect()->route('projects.index')->with('flash', ['type' => 'success', 'message' => 'Project berhasil ditambahkan.']);
    }

    public function show(Request $request, Project $project)
    {
        $project->load(['payments', 'cashIns.creator', 'cashOuts.creator', 'teamDistributions.user', 'referrals', 'tasks.assignee', 'projectTypeDefinition']);
        $project->load(['materials.product', 'materials.warehouse']);
        $paymentTimeline = $this->paymentTimelineRows($project);
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
        $cashOutCategories = $this->cashCategoryOptions('cash_out');
        $cashCategoryLabels = $cashOutCategories->pluck('label', 'value');
        $convertedBudget = ProjectBudget::query()
            ->with('items')
            ->where('converted_project_id', $project->id)
            ->first();
        $budgetItems = $convertedBudget?->items ?? collect();
        $materialItems = $project->materials;
        $directMaterialTotalCost = (float) $materialItems->sum(fn ($item) => (float) $item->planned_qty * (float) $item->unit_cost);
        $directMaterialTotalPrice = (float) $materialItems->sum(fn ($item) => (float) $item->planned_qty * (float) $item->unit_price);
        $budgetTotalCost = (float) $budgetItems->sum(fn ($item) => (float) $item->qty * (float) $item->unit_cost);
        $budgetTotalPrice = (float) $budgetItems->sum(fn ($item) => (float) $item->qty * (float) $item->unit_price);
        $hasBudgetItems = $budgetItems->isNotEmpty();

        $availableWarehouses = $this->projectWarehouseQuery($request)->get(['id', 'code', 'name']);
        $availableWarehouseIds = $availableWarehouses->pluck('id');

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'client_contact' => $project->client_contact,
                'crm_customer_id' => $project->crm_customer_id,
                'project_type' => $project->project_type,
                'project_type_label' => $project->projectTypeLabel(),
                'supports_budget_items' => $project->supportsBudgetItems(),
                'supports_project_board' => $project->supportsProjectBoard(),
                'total_value' => (float) $project->total_value,
                'status' => $project->status,
                'created_at' => $project->created_at?->format('Y-m-d'),
                'started_at' => $project->started_at?->format('Y-m-d'),
                'finished_at' => $project->finished_at?->format('Y-m-d'),
                'invoiced_at' => $project->invoiced_at?->format('Y-m-d'),
                'description' => $project->description,
                'payments' => $paymentTimeline,
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
                    'paid_at' => $d->paid_at?->format('Y-m-d'),
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
                ],
                'budget_summary' => [
                    'budget_id' => $convertedBudget?->id,
                    'source' => $hasBudgetItems ? 'budget' : 'materials',
                    'item_count' => $hasBudgetItems ? $budgetItems->count() : $materialItems->count(),
                    'total_cost' => $hasBudgetItems ? $budgetTotalCost : $directMaterialTotalCost,
                    'total_price' => $hasBudgetItems ? $budgetTotalPrice : $directMaterialTotalPrice,
                    'total_margin' => $hasBudgetItems ? ($budgetTotalPrice - $budgetTotalCost) : ($directMaterialTotalPrice - $directMaterialTotalCost),
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
                    'unit_cost' => (float) $m->unit_cost,
                    'unit_price' => (float) $m->unit_price,
                    'subtotal_cost' => (float) $m->planned_qty * (float) $m->unit_cost,
                    'subtotal_price' => (float) $m->planned_qty * (float) $m->unit_price,
                    'margin_amount' => ((float) $m->planned_qty * (float) $m->unit_price) - ((float) $m->planned_qty * (float) $m->unit_cost),
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
                ->whereIn('sales_channel', ['project', 'both'])
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'category', 'uom', 'sales_channel', 'product_type', 'selling_price']),
            'warehouses' => $availableWarehouses,
            'warehouse_stocks' => MasterProductWarehouseStock::query()
                ->when($availableWarehouseIds->isNotEmpty(), fn ($query) => $query->whereIn('warehouse_id', $availableWarehouseIds))
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
            'cash_accounts' => Account::cashBankOptions(),
            'cash_category_options' => [
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
        if (! $project->supportsProjectBoard()) {
            throw ValidationException::withMessages([
                'project' => 'Kanban task hanya tersedia untuk tipe project yang mengaktifkan board task.',
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
        $project->loadMissing('projectTypeDefinition');

        $canEditPayments = ! $project->payments()->whereNotNull('paid_at')->exists();

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'client_contact' => $project->client_contact,
                'crm_customer_id' => $project->crm_customer_id,
                'project_type' => $project->project_type,
                'project_type_label' => $project->projectTypeLabel(),
                'supports_budget_items' => $project->supportsBudgetItems(),
                'supports_project_board' => $project->supportsProjectBoard(),
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
            'crm_customers' => $this->crmCustomerOptions(),
            'project_types' => $this->projectTypeOptions(),
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
            'crm_customer_id' => 'required|exists:crm_customers,id',
            'project_type' => [
                'nullable',
                Rule::exists('project_types', 'key')->where('is_active', true),
            ],
            'total_value' => 'required|numeric|min:0',
            'status' => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'legal_vault_path' => 'nullable|string|max:2000',
        ];

        if ($canEditPayments) {
            $rules['payments'] = 'nullable|array|min:1|max:20';
            $rules['payments.*.percentage'] = 'required|numeric|min:0.01|max:100';
            $rules['payments.*.note'] = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);
        $validated['total_value'] = (float) $validated['total_value'];
        $validated['project_type'] = $validated['project_type'] ?? $project->project_type ?? ProjectType::defaultKey();
        $validated = array_merge($validated, $this->projectClientSnapshot((int) $validated['crm_customer_id']));

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
            if ($validated['total_value'] > 0) {
                if (empty($validated['payments'])) {
                    throw ValidationException::withMessages([
                        'payments' => 'Jadwal termin wajib diisi jika project memiliki nilai kontrak.',
                    ]);
                }

                $this->assertPaymentsTotalHundredPercent($validated['payments']);
            } else {
                $validated['payments'] = [];
            }
        }

        DB::transaction(function () use ($project, $validated, $canEditPayments) {
            $project->update(Arr::except($validated, ['payments']));

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

        DB::transaction(function () use ($project, $validated): void {
            $project->update([
                'status' => 'selesai',
                'finished_at' => $validated['finished_at'],
            ]);

            app(ProjectMaterialReservationService::class)->releaseProjectReservations($project->fresh('materials'));
        });

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
                    ->whereIn('sales_channel', ['project', 'both']),
            ],
            'warehouse_id' => 'required|exists:warehouses,id',
            'planned_qty' => 'required|numeric|min:0.01',
            'unit_cost' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $product = MasterProduct::query()->findOrFail((int) $validated['master_product_id']);
        $selectedWarehouseId = (int) $validated['warehouse_id'];
        $allowedWarehouseIds = $this->projectWarehouseQuery($request)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($allowedWarehouseIds !== [] && ! in_array($selectedWarehouseId, $allowedWarehouseIds, true)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang tidak tersedia pada konteks perusahaan aktif.',
            ]);
        }

        if ($product->isStockTracked() && $product->warehouse_id !== null && (int) $product->warehouse_id !== $selectedWarehouseId) {
            throw ValidationException::withMessages([
                'master_product_id' => 'Produk ini tidak terdaftar untuk gudang yang dipilih.',
            ]);
        }

        DB::transaction(function () use ($project, $validated, $product): void {
            $stock = null;
            $available = 0;
            if ($product->isStockTracked()) {
                $stock = MasterProductWarehouseStock::query()
                    ->lockForUpdate()
                    ->firstOrCreate(
                        [
                            'master_product_id' => $product->id,
                            'warehouse_id' => (int) $validated['warehouse_id'],
                        ],
                        ['qty' => 0, 'reserved_qty' => 0],
                    );

                $available = max((float) $stock->qty - (float) $stock->reserved_qty, 0);
            }
            $plannedQty = (float) $validated['planned_qty'];
            $toReserve = min($plannedQty, $available);
            $unitCost = (float) ($validated['unit_cost'] ?? 0);
            $unitPrice = array_key_exists('unit_price', $validated)
                ? (float) $validated['unit_price']
                : (float) $product->selling_price;

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

            $previousQty = (float) $material->planned_qty;
            $newTotalQty = $previousQty + $plannedQty;
            $material->planned_qty = $newTotalQty;
            $material->reserved_qty = (float) $material->reserved_qty + $toReserve;
            $material->unit_cost = $newTotalQty > 0
                ? (($previousQty * (float) $material->unit_cost) + ($plannedQty * $unitCost)) / $newTotalQty
                : $unitCost;
            $material->unit_price = $newTotalQty > 0
                ? (($previousQty * (float) $material->unit_price) + ($plannedQty * $unitPrice)) / $newTotalQty
                : $unitPrice;
            $material->notes = $validated['notes'] ?? $material->notes;
            $material->status = $product->isStockTracked()
                ? $this->projectMaterialStatus($material)
                : 'ready';
            $material->save();

            if ($stock) {
                app(ProjectMaterialReservationService::class)
                    ->syncWarehouseReservation($product->id, (int) $validated['warehouse_id']);
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kebutuhan project berhasil ditambahkan. Item stok akan di-reserve, sedangkan jasa/non-stok tidak masuk perencanaan PO.']);
    }

    public function materialProductSearch(Request $request, Project $project)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $warehouseId = (int) $validated['warehouse_id'];
        $allowedWarehouseIds = $this->projectWarehouseQuery($request)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($allowedWarehouseIds !== [] && ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang tidak tersedia pada konteks perusahaan aktif.',
            ]);
        }
        $stocks = MasterProductWarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->get(['master_product_id', 'qty', 'reserved_qty'])
            ->keyBy('master_product_id');

        $products = MasterProduct::query()
            ->where('status', 'active')
            ->whereIn('sales_channel', ['project', 'both'])
            ->where(function ($query) use ($warehouseId): void {
                $query
                    ->where('product_type', MasterProduct::PRODUCT_TYPE_SERVICE)
                    ->orWhere('warehouse_id', $warehouseId)
                    ->orWhere(function ($legacyQuery) use ($warehouseId): void {
                        $legacyQuery
                            ->whereNull('warehouse_id')
                            ->whereHas('warehouseStocks', fn ($stock) => $stock->where('warehouse_id', $warehouseId));
                    });
            })
            ->when($keyword !== '', function ($query) use ($keyword) {
                $operator = $this->caseInsensitiveLikeOperator();
                $query->where(function ($nested) use ($keyword, $operator) {
                    $nested
                        ->where('sku', $operator, "%{$keyword}%")
                        ->orWhere('name', $operator, "%{$keyword}%")
                        ->orWhere('category', $operator, "%{$keyword}%")
                        ->orWhere('uom', $operator, "%{$keyword}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'category', 'uom', 'sales_channel', 'product_type', 'selling_price'])
            ->map(function (MasterProduct $product) use ($stocks): array {
                $stock = $stocks->get($product->id);

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'uom' => $product->uom,
                    'sales_channel' => $product->sales_channel,
                    'product_type' => $product->product_type,
                    'selling_price' => (float) $product->selling_price,
                    'available' => $product->isStockTracked()
                        ? ($stock ? max((float) $stock->qty - (float) $stock->reserved_qty, 0) : 0)
                        : null,
                ];
            })
            ->values();

        return response()->json(['products' => $products]);
    }

    private function caseInsensitiveLikeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function projectWarehouseQuery(Request $request)
    {
        $query = Warehouse::query()->where('is_active', true)->orderBy('name');
        $currentCompanyId = ErpCompanyResolver::currentCompanyIdForSession($request);
        if (! $currentCompanyId) {
            return $query;
        }

        $companyWarehouseIds = Warehouse::query()
            ->where('is_active', true)
            ->where('company_id', $currentCompanyId)
            ->pluck('id');

        if ($companyWarehouseIds->isNotEmpty()) {
            $query->whereIn('id', $companyWarehouseIds);
        }

        return $query;
    }

    public function destroyMaterial(Project $project, ProjectMaterial $material)
    {
        if ($material->project_id !== $project->id) {
            abort(404);
        }

        DB::transaction(function () use ($material): void {
            $productId = (int) $material->master_product_id;
            $warehouseId = (int) $material->warehouse_id;
            $material->delete();

            if ($warehouseId > 0) {
                app(ProjectMaterialReservationService::class)
                    ->syncWarehouseReservation($productId, $warehouseId);
            }
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

    private function paymentTimelineRows(Project $project)
    {
        $cashIns = $project->cashIns
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();
        $cashIndex = 0;
        $paidRunning = 0.0;
        $requiredRunning = 0.0;

        return $project->payments->map(function (ProjectPayment $payment) use ($cashIns, &$cashIndex, &$paidRunning, &$requiredRunning): array {
            $requiredRunning += (float) $payment->amount;
            $coveredAt = null;

            while ($cashIndex < $cashIns->count() && $paidRunning + 0.00001 < $requiredRunning) {
                $cash = $cashIns[$cashIndex];
                $paidRunning += (float) $cash->amount;
                $coveredAt = $cash->date?->format('Y-m-d');
                $cashIndex++;
            }

            $isPaid = $payment->paid_at !== null || $paidRunning + 0.00001 >= $requiredRunning;

            return [
                'id' => $payment->id,
                'term_number' => $payment->term_number,
                'percentage' => (float) $payment->percentage,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'timeline_paid_at' => $payment->paid_at?->format('Y-m-d') ?? ($isPaid ? $coveredAt : null),
                'is_paid' => $isPaid,
                'note' => $payment->note,
            ];
        });
    }

    private function cashCategoryOptions(string $domain)
    {
        return CashCategory::query()
            ->where('domain', $domain)
            ->whereNotIn('key', CashCategory::retiredKeysFor($domain))
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

    private function crmCustomerOptions()
    {
        return CrmCustomer::query()
            ->where('is_active', true)
            ->orderBy('company')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'company', 'email', 'phone'])
            ->map(fn (CrmCustomer $customer): array => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'company' => $customer->company,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'display_name' => $customer->company ?: $customer->name,
                'contact' => collect([$customer->phone, $customer->email])->filter()->implode(' / '),
            ])
            ->values();
    }

    private function projectClientSnapshot(int $customerId): array
    {
        $customer = CrmCustomer::query()->findOrFail($customerId);

        return [
            'client_name' => $customer->company ?: $customer->name,
            'client_contact' => collect([$customer->phone, $customer->email])->filter()->implode(' / ') ?: null,
        ];
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

    private function projectTypeOptions(): array
    {
        return ProjectType::activeOptions()->all();
    }
}
