<?php

use App\Http\Controllers\CashflowController;
use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\CmsMediaController;
use App\Http\Controllers\CmsModuleController;
use App\Http\Controllers\CrmActivityController;
use App\Http\Controllers\CrmCustomerController;
use App\Http\Controllers\CrmLeadController;
use App\Http\Controllers\CrmPipelineController;
use App\Http\Controllers\ERPAccountingCoaSettingsController;
use App\Http\Controllers\ERPAdministrationMasterDataController;
use App\Http\Controllers\ErpCalendarController;
use App\Http\Controllers\ErpChatbotController;
use App\Http\Controllers\ERPInventoryController;
use App\Http\Controllers\ERPInventoryMasterDataController;
use App\Http\Controllers\ERPMasterProductController;
use App\Http\Controllers\ERPModuleController;
use App\Http\Controllers\ERPPurchasingController;
use App\Http\Controllers\ERPReportingController;
use App\Http\Controllers\ERPSalesController;
use App\Http\Controllers\ErpSystemLogController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\HREmployeeController;
use App\Http\Controllers\HRLegalController;
use App\Http\Controllers\LabelProfileController;
use App\Http\Controllers\OperationalController;
use App\Http\Controllers\PersonalFinanceController;
use App\Http\Controllers\PersonalModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBudgetController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ProjectRoleController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamDistributionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRolePermissionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (! $disk->exists($path)) {
        abort(404);
    }

    return response()->file($disk->path($path));
})->where('path', '.*')->name('storage.serve');

Route::get('/', [PublicHomeController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('erp/chatbot/ask', [ErpChatbotController::class, 'ask'])->name('erp.chatbot.ask');

    // Projects (Admin + Manajer)
    Route::middleware('role:admin|manajer')->group(function () {
        // ERP Module Landing Pages
        Route::get('erp/accounting', [ERPModuleController::class, 'accounting'])->name('erp.accounting');
        Route::get('erp/accounting/cashflow', [CashflowController::class, 'index'])->name('erp.accounting.cashflow');
        Route::post('erp/accounting/cashflow', [CashflowController::class, 'store'])->name('erp.accounting.cashflow.store');
        Route::patch('erp/accounting/cashflow/cash-in/{cashIn}', [CashflowController::class, 'updateCashIn'])->name('erp.accounting.cashflow.cash-in.update');
        Route::delete('erp/accounting/cashflow/cash-in/{cashIn}', [CashflowController::class, 'destroyCashIn'])->name('erp.accounting.cashflow.cash-in.destroy');
        Route::patch('erp/accounting/cashflow/cash-out/{cashOut}', [CashflowController::class, 'updateCashOut'])->name('erp.accounting.cashflow.cash-out.update');
        Route::delete('erp/accounting/cashflow/cash-out/{cashOut}', [CashflowController::class, 'destroyCashOut'])->name('erp.accounting.cashflow.cash-out.destroy');
        Route::get('erp/accounting/operational', [OperationalController::class, 'index'])->name('erp.accounting.operational');
        Route::post('erp/accounting/operational', [OperationalController::class, 'store'])->name('erp.accounting.operational.store');
        Route::patch('erp/accounting/operational/{cashOut}', [OperationalController::class, 'update'])->name('erp.accounting.operational.update');
        Route::delete('erp/accounting/operational/{cashOut}', [OperationalController::class, 'destroy'])->name('erp.accounting.operational.destroy');
        Route::get('erp/accounting/expense-categories', [ExpenseCategoryController::class, 'index'])->name('erp.accounting.expense-categories');
        Route::post('erp/accounting/expense-categories', [ExpenseCategoryController::class, 'upsert'])->name('erp.accounting.expense-categories.upsert');
        Route::post('erp/accounting/expense-categories/store', [ExpenseCategoryController::class, 'storeCategory'])->name('erp.accounting.expense-categories.store');
        Route::get('erp/accounting/coa-settings', [ERPAccountingCoaSettingsController::class, 'index'])->name('erp.accounting.coa-settings');
        Route::post('erp/accounting/coa-settings', [ERPAccountingCoaSettingsController::class, 'upsert'])->name('erp.accounting.coa-settings.upsert');
        Route::post('erp/accounting/coa-settings/category-mappings', [ERPAccountingCoaSettingsController::class, 'upsertCategoryMapping'])->name('erp.accounting.coa-settings.category-mappings.upsert');
        Route::post('erp/accounting/coa-settings/categories', [ERPAccountingCoaSettingsController::class, 'storeCategory'])->name('erp.accounting.coa-settings.categories.store');
        Route::post('erp/accounting/coa-settings/apply-defaults', [ERPAccountingCoaSettingsController::class, 'applyDefaults'])->name('erp.accounting.coa-settings.apply-defaults');
        Route::get('erp/accounting/payments', [ERPModuleController::class, 'payments'])->name('erp.accounting.payments');
        Route::get('erp/accounting/reconciliation', [ReconciliationController::class, 'index'])->name('erp.accounting.reconciliation');
        Route::get('erp/sales', [ERPModuleController::class, 'sales'])->name('erp.sales');
        Route::get('erp/purchasing', [ERPModuleController::class, 'purchasing'])->name('erp.purchasing');
        Route::get('erp/inventory', [ERPModuleController::class, 'inventory'])->name('erp.inventory');
        Route::get('erp/projects', [ERPModuleController::class, 'projects'])->name('erp.projects');
        Route::get('erp/hr', [ERPModuleController::class, 'hr'])->name('erp.hr');
        Route::get('erp/crm', [ERPModuleController::class, 'crm'])->name('erp.crm');
        Route::get('erp/crm/leads', [CrmLeadController::class, 'index'])->name('erp.crm.leads');
        Route::post('erp/crm/leads', [CrmLeadController::class, 'store'])->name('erp.crm.leads.store');
        Route::patch('erp/crm/leads/{crmLead}', [CrmLeadController::class, 'update'])->name('erp.crm.leads.update');
        Route::delete('erp/crm/leads/{crmLead}', [CrmLeadController::class, 'destroy'])->name('erp.crm.leads.destroy');
        Route::get('erp/crm/customers', [CrmCustomerController::class, 'index'])->name('erp.crm.customers');
        Route::post('erp/crm/customers', [CrmCustomerController::class, 'store'])->name('erp.crm.customers.store');
        Route::patch('erp/crm/customers/{crmCustomer}', [CrmCustomerController::class, 'update'])->name('erp.crm.customers.update');
        Route::delete('erp/crm/customers/{crmCustomer}', [CrmCustomerController::class, 'destroy'])->name('erp.crm.customers.destroy');
        Route::get('erp/crm/pipelines', [CrmPipelineController::class, 'index'])->name('erp.crm.pipelines');
        Route::post('erp/crm/pipelines', [CrmPipelineController::class, 'store'])->name('erp.crm.pipelines.store');
        Route::patch('erp/crm/pipelines/{crmPipeline}', [CrmPipelineController::class, 'update'])->name('erp.crm.pipelines.update');
        Route::delete('erp/crm/pipelines/{crmPipeline}', [CrmPipelineController::class, 'destroy'])->name('erp.crm.pipelines.destroy');
        Route::get('erp/crm/activities', [CrmActivityController::class, 'index'])->name('erp.crm.activities');
        Route::post('erp/crm/activities', [CrmActivityController::class, 'store'])->name('erp.crm.activities.store');
        Route::patch('erp/crm/activities/{crmActivity}', [CrmActivityController::class, 'update'])->name('erp.crm.activities.update');
        Route::delete('erp/crm/activities/{crmActivity}', [CrmActivityController::class, 'destroy'])->name('erp.crm.activities.destroy');
        Route::get('erp/hr/employees', [HREmployeeController::class, 'index'])->name('erp.hr.employees');
        Route::post('erp/hr/employees', [HREmployeeController::class, 'store'])->name('erp.hr.employees.store');
        Route::patch('erp/hr/employees/{employee}', [HREmployeeController::class, 'update'])->name('erp.hr.employees.update');
        Route::delete('erp/hr/employees/{employee}', [HREmployeeController::class, 'destroy'])->name('erp.hr.employees.destroy');
        Route::get('erp/hr/legal', [HRLegalController::class, 'index'])->name('erp.hr.legal');
        Route::get('erp/hr/legal/templates/{file}', [HRLegalController::class, 'downloadTemplate'])->name('erp.hr.legal.templates.download');
        Route::post('erp/hr/legal/folders', [HRLegalController::class, 'storeFolder'])->name('erp.hr.legal.folders.store');
        Route::post('erp/hr/legal/uploads', [HRLegalController::class, 'upload'])->name('erp.hr.legal.uploads.store');
        Route::delete('erp/hr/legal/items', [HRLegalController::class, 'destroyItem'])->name('erp.hr.legal.items.destroy');
        Route::get('erp/hr/legal/files/download', [HRLegalController::class, 'downloadFile'])->name('erp.hr.legal.files.download');
        Route::get('erp/hr/legal/files/view', [HRLegalController::class, 'viewFile'])->name('erp.hr.legal.files.view');
        Route::get('erp/calendar', [ErpCalendarController::class, 'index'])->name('erp.calendar');
        Route::get('erp/reporting', [ERPModuleController::class, 'reporting'])->name('erp.reporting');
        Route::get('erp/master-products', [ERPMasterProductController::class, 'index'])->name('erp.master-products.index');
        Route::get('erp/master-products/preview-codes', [ERPMasterProductController::class, 'previewCodes'])->name('erp.master-products.preview-codes');
        Route::post('erp/master-products', [ERPMasterProductController::class, 'store'])->name('erp.master-products.store');
        Route::get('erp/master-products/{masterProduct}', [ERPMasterProductController::class, 'show'])->name('erp.master-products.show');
        Route::post('erp/master-products/{masterProduct}/print-barcode', [ERPMasterProductController::class, 'printBarcode'])->name('erp.master-products.print-barcode');
        Route::patch('erp/master-products/{masterProduct}', [ERPMasterProductController::class, 'update'])->name('erp.master-products.update');
        Route::delete('erp/master-products/{masterProduct}', [ERPMasterProductController::class, 'destroy'])->name('erp.master-products.destroy');
        Route::post('erp/master-products/{masterProduct}/uom-mappings', [ERPMasterProductController::class, 'storeUomMapping'])->name('erp.master-products.uom-mappings.store');
        Route::patch('erp/master-products/{masterProduct}/uom-mappings/{mapping}', [ERPMasterProductController::class, 'updateUomMapping'])->name('erp.master-products.uom-mappings.update');
        Route::delete('erp/master-products/{masterProduct}/uom-mappings/{mapping}', [ERPMasterProductController::class, 'destroyUomMapping'])->name('erp.master-products.uom-mappings.destroy');
        Route::get('erp/inventory/categories', [ERPInventoryMasterDataController::class, 'categories'])->name('erp.inventory.categories');
        Route::post('erp/inventory/categories', [ERPInventoryMasterDataController::class, 'storeCategory'])->name('erp.inventory.categories.store');
        Route::get('erp/inventory/warehouses', [ERPInventoryMasterDataController::class, 'warehouses'])->name('erp.inventory.warehouses');
        Route::post('erp/inventory/warehouses', [ERPInventoryMasterDataController::class, 'storeWarehouse'])->name('erp.inventory.warehouses.store');
        Route::patch('erp/inventory/warehouses/{warehouse}', [ERPInventoryMasterDataController::class, 'updateWarehouse'])->name('erp.inventory.warehouses.update');
        Route::get('erp/inventory/uoms', [ERPInventoryMasterDataController::class, 'uoms'])->name('erp.inventory.uoms');
        Route::post('erp/inventory/uoms', [ERPInventoryMasterDataController::class, 'storeUom'])->name('erp.inventory.uoms.store');
        Route::patch('erp/inventory/uoms/{uom}', [ERPInventoryMasterDataController::class, 'updateUom'])->name('erp.inventory.uoms.update');
        Route::delete('erp/inventory/uoms/{uom}', [ERPInventoryMasterDataController::class, 'destroyUom'])->name('erp.inventory.uoms.destroy');
        Route::post('erp/inventory/uom-conversions', [ERPInventoryMasterDataController::class, 'storeConversion'])->name('erp.inventory.uom-conversions.store');
        Route::patch('erp/inventory/uom-conversions/{uomConversion}', [ERPInventoryMasterDataController::class, 'updateConversion'])->name('erp.inventory.uom-conversions.update');
        Route::delete('erp/inventory/uom-conversions/{uomConversion}', [ERPInventoryMasterDataController::class, 'destroyConversion'])->name('erp.inventory.uom-conversions.destroy');
        Route::get('erp/inventory/stock-management', [ERPInventoryController::class, 'stockManagement'])->name('erp.inventory.stock-management');
        Route::put('erp/inventory/stock-management/{masterProduct}', [ERPInventoryController::class, 'updateStock'])->name('erp.inventory.stock-management.update');
        Route::get('erp/inventory/stock-opname', [ERPInventoryController::class, 'stockOpname'])->name('erp.inventory.stock-opname');
        Route::post('erp/inventory/stock-opname', [ERPInventoryController::class, 'storeStockOpname'])->name('erp.inventory.stock-opname.store');
        Route::get('erp/inventory/stock-transfer', [ERPInventoryController::class, 'stockTransfer'])->name('erp.inventory.stock-transfer');
        Route::post('erp/inventory/stock-transfer', [ERPInventoryController::class, 'storeStockTransfer'])->name('erp.inventory.stock-transfer.store');
        Route::get('erp/inventory/stock-report', [ERPInventoryController::class, 'stockReport'])->name('erp.inventory.stock-report');
        Route::get('erp/inventory/stock-movements', [ERPInventoryController::class, 'stockMovements'])->name('erp.inventory.stock-movements');
        Route::get('erp/purchasing/suppliers', [ERPPurchasingController::class, 'suppliers'])->name('erp.purchasing.suppliers');
        Route::post('erp/purchasing/suppliers', [ERPPurchasingController::class, 'storeSupplier'])->name('erp.purchasing.suppliers.store');
        Route::get('erp/purchasing/suppliers/{supplier}', [ERPPurchasingController::class, 'supplierShow'])->name('erp.purchasing.suppliers.show');
        Route::get('erp/purchasing/purchase-orders', [ERPPurchasingController::class, 'purchaseOrders'])->name('erp.purchasing.purchase-orders');
        Route::post('erp/purchasing/purchase-orders', [ERPPurchasingController::class, 'storePurchaseOrder'])->name('erp.purchasing.purchase-orders.store');
        Route::get('erp/purchasing/purchase-orders/{purchaseOrder}', [ERPPurchasingController::class, 'purchaseOrderShow'])->name('erp.purchasing.purchase-orders.show');
        Route::put('erp/purchasing/purchase-orders/{purchaseOrder}', [ERPPurchasingController::class, 'updatePurchaseOrder'])->name('erp.purchasing.purchase-orders.update');
        Route::post('erp/purchasing/purchase-orders/{purchaseOrder}/advance', [ERPPurchasingController::class, 'advancePurchaseOrder'])->name('erp.purchasing.purchase-orders.advance');
        Route::get('erp/purchasing/goods-receipts', [ERPPurchasingController::class, 'goodsReceipts'])->name('erp.purchasing.goods-receipts');
        Route::post('erp/purchasing/goods-receipts', [ERPPurchasingController::class, 'storeGoodsReceipt'])->name('erp.purchasing.goods-receipts.store');
        Route::get('erp/purchasing/goods-receipts/{goodsReceipt}', [ERPPurchasingController::class, 'goodsReceiptShow'])->name('erp.purchasing.goods-receipts.show');
        Route::post('erp/purchasing/goods-receipts/{goodsReceipt}/advance', [ERPPurchasingController::class, 'advanceGoodsReceipt'])->name('erp.purchasing.goods-receipts.advance');
        Route::get('erp/purchasing/reorder-planning', [ERPPurchasingController::class, 'reorderPlanning'])->name('erp.purchasing.reorder-planning');
        Route::get('erp/purchasing/reorder-planning/{masterProduct}', [ERPPurchasingController::class, 'reorderShow'])->name('erp.purchasing.reorder-planning.show');
        Route::get('erp/sales/pos', [ERPSalesController::class, 'pos'])->name('erp.sales.pos');
        Route::post('erp/sales/pos/checkout', [ERPSalesController::class, 'checkoutPos'])->name('erp.sales.pos.checkout');
        Route::post('erp/sales/pos/print-receipt', [ERPSalesController::class, 'printPosReceipt'])->name('erp.sales.pos.print-receipt');
        Route::get('erp/sales/transactions', [ERPSalesController::class, 'posTransactions'])->name('erp.sales.pos.transactions');
        Route::get('erp/sales/transactions/{posSale}', [ERPSalesController::class, 'posTransactionShow'])->name('erp.sales.pos.transactions.show');
        Route::patch('erp/sales/transactions/{posSale}/payment-method', [ERPSalesController::class, 'updatePosTransactionPaymentMethod'])->name('erp.sales.pos.transactions.payment-method.update');
        Route::post('erp/sales/transactions/{posSale}/refund', [ERPSalesController::class, 'refundPosTransaction'])->name('erp.sales.pos.transactions.refund');
        Route::post('erp/sales/transactions/{posSale}/reopen', [ERPSalesController::class, 'reopenPosTransaction'])->name('erp.sales.pos.transactions.reopen');
        Route::get('erp/sales/project-invoices', [ERPSalesController::class, 'projectInvoices'])->name('erp.sales.project-invoices');
        Route::get('erp/sales/project-invoices/{project}', [ERPSalesController::class, 'projectInvoiceShow'])->name('erp.sales.project-invoices.show');
        Route::post('erp/sales/project-invoices/{project}/payments', [ERPSalesController::class, 'storeProjectInvoicePayment'])->name('erp.sales.project-invoices.payments.store');
        Route::patch('erp/sales/project-invoices/{project}/payments/{cashIn}', [ERPSalesController::class, 'updateProjectInvoicePayment'])->name('erp.sales.project-invoices.payments.update');
        Route::get('erp/sales/project-invoices/{project}/download', [ERPSalesController::class, 'downloadProjectInvoice'])->name('erp.sales.project-invoices.download');
        Route::get('erp/sales/project-invoices/{project}/payments/{cashIn}/receipt', [ERPSalesController::class, 'downloadProjectReceipt'])->name('erp.sales.project-invoices.receipt');

        Route::resource('projects', ProjectController::class);
        Route::get('erp/projects/budgets', [ProjectBudgetController::class, 'index'])->name('erp.projects.budgets.index');
        Route::post('erp/projects/budgets', [ProjectBudgetController::class, 'store'])->name('erp.projects.budgets.store');
        Route::get('erp/projects/budgets/{budget}', [ProjectBudgetController::class, 'show'])->name('erp.projects.budgets.show');
        Route::put('erp/projects/budgets/{budget}', [ProjectBudgetController::class, 'update'])->name('erp.projects.budgets.update');
        Route::patch('erp/projects/budgets/{budget}/deal', [ProjectBudgetController::class, 'markDeal'])->name('erp.projects.budgets.deal');
        Route::post('erp/projects/budgets/{budget}/convert', [ProjectBudgetController::class, 'convert'])->name('erp.projects.budgets.convert');
        Route::get('erp/projects/budgets/{budget}/pdf', [ProjectBudgetController::class, 'pdf'])->name('erp.projects.budgets.pdf');
        Route::post('projects/{project}/materials', [ProjectController::class, 'storeMaterial'])->name('projects.materials.store');
        Route::get('projects/{project}/material-products/search', [ProjectController::class, 'materialProductSearch'])->name('projects.material-products.search');
        Route::delete('projects/{project}/materials/{material}', [ProjectController::class, 'destroyMaterial'])->name('projects.materials.destroy');
        Route::post('projects/{project}/legal-folder', [ProjectController::class, 'createLegalFolder'])->name('projects.legal-folder.create');
        Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status.update');
        Route::post('projects/{project}/team-members', [ProjectController::class, 'storeTeamMember'])->name('projects.team-members.store');
        Route::delete('projects/{project}/team-members/{teamDistribution}', [ProjectController::class, 'destroyTeamMember'])->name('projects.team-members.destroy');
        Route::post('projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
        Route::patch('projects/{project}/tasks/{task}', [ProjectController::class, 'updateTask'])->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{task}', [ProjectController::class, 'destroyTask'])->name('projects.tasks.destroy');
        Route::get('erp/projects/team-roles', [ProjectRoleController::class, 'index'])->name('erp.projects.team-roles.index');
        Route::post('erp/projects/team-roles', [ProjectRoleController::class, 'store'])->name('erp.projects.team-roles.store');
        Route::delete('erp/projects/team-roles/{teamRole}', [ProjectRoleController::class, 'destroy'])->name('erp.projects.team-roles.destroy');

        // Termin
        Route::patch('project-payments/{payment}/mark-paid', [ProjectPaymentController::class, 'markPaid'])->name('project-payments.mark-paid');
        Route::patch('project-payments/{payment}/mark-unpaid', [ProjectPaymentController::class, 'markUnpaid'])->name('project-payments.mark-unpaid');

        // Kas Masuk
        Route::get('kas-masuk', [CashInController::class, 'index'])->name('cash-in.index');
        Route::post('kas-masuk', [CashInController::class, 'store'])->name('cash-in.store');
        Route::put('kas-masuk/{cashIn}', [CashInController::class, 'update'])->name('cash-in.update');
        Route::delete('kas-masuk/{cashIn}', [CashInController::class, 'destroy'])->name('cash-in.destroy');

        // Kas Keluar
        Route::get('kas-keluar', [CashOutController::class, 'index'])->name('cash-out.index');
        Route::post('kas-keluar', [CashOutController::class, 'store'])->name('cash-out.store');
        Route::put('kas-keluar/{cashOut}', [CashOutController::class, 'update'])->name('cash-out.update');
        Route::delete('kas-keluar/{cashOut}', [CashOutController::class, 'destroy'])->name('cash-out.destroy');

        // Referrals (via Project show page)
        Route::post('referrals', [ReferralController::class, 'store'])->name('referrals.store');
        Route::put('referrals/{referral}', [ReferralController::class, 'update'])->name('referrals.update');
        Route::delete('referrals/{referral}', [ReferralController::class, 'destroy'])->name('referrals.destroy');

        // Team Distribution
        Route::get('team-distribution/calculator', [TeamDistributionController::class, 'calculator'])->name('team-distribution.calculator');
        Route::post('team-distribution/save', [TeamDistributionController::class, 'save'])->name('team-distribution.save');

        // Reports
        Route::get('laporan/project-profit', [ReportController::class, 'projectProfit'])->name('reports.project-profit');
        Route::get('laporan/bulanan', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('laporan/anggota', [ReportController::class, 'memberPayments'])->name('reports.member-payments');
        Route::get('erp/accounting/chart-of-accounts', [ERPReportingController::class, 'chartOfAccounts'])->name('erp.accounting.coa');
        Route::post('erp/accounting/chart-of-accounts', [ERPReportingController::class, 'storeChartOfAccount'])->name('erp.accounting.coa.store');
        Route::get('laporan/general-ledger', [ERPReportingController::class, 'generalLedger'])->name('reports.general-ledger');
        Route::get('laporan/neraca-saldo', [ERPReportingController::class, 'trialBalance'])->name('reports.trial-balance');

        // Export
        Route::get('export/project-profit', [ReportController::class, 'exportProjectProfitExcel'])->name('export.project-profit');
        Route::get('export/bulanan', [ReportController::class, 'exportMonthlyExcel'])->name('export.monthly');
        Route::get('export/anggota', [ReportController::class, 'exportMemberPaymentsExcel'])->name('export.member-payments');

        // Personal — keuangan pribadi & keluarga (terpisah dari ERP bisnis)
        Route::get('personal', [PersonalModuleController::class, 'index'])->name('personal');
        Route::get('personal/overview', [PersonalFinanceController::class, 'overview'])->name('personal.overview');
        Route::get('personal/transactions', [PersonalFinanceController::class, 'transactions'])->name('personal.transactions');
        Route::post('personal/transactions', [PersonalFinanceController::class, 'storeTransaction'])->name('personal.transactions.store');
        Route::patch('personal/transactions/{transaction}', [PersonalFinanceController::class, 'updateTransaction'])->name('personal.transactions.update');
        Route::delete('personal/transactions/{transaction}', [PersonalFinanceController::class, 'destroyTransaction'])->name('personal.transactions.destroy');
        Route::post('personal/categories', [PersonalFinanceController::class, 'storeCategory'])->name('personal.categories.store');
        Route::get('personal/budgets', [PersonalFinanceController::class, 'budgets'])->name('personal.budgets');
        Route::post('personal/budgets', [PersonalFinanceController::class, 'storeBudget'])->name('personal.budgets.store');
        Route::get('personal/investments', [PersonalFinanceController::class, 'investments'])->name('personal.investments');
        Route::post('personal/investments', [PersonalFinanceController::class, 'storeInvestment'])->name('personal.investments.store');
        Route::patch('personal/investments/{investment}', [PersonalFinanceController::class, 'updateInvestment'])->name('personal.investments.update');
        Route::delete('personal/investments/{investment}', [PersonalFinanceController::class, 'destroyInvestment'])->name('personal.investments.destroy');
        Route::post('personal/investments/{investment}/movements', [PersonalFinanceController::class, 'storeInvestmentMovement'])->name('personal.investments.movements.store');
    });

    // User Management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('users/accounts', [UserController::class, 'index'])->name('users.accounts');
        Route::get('users/roles-permissions', [UserRolePermissionController::class, 'index'])->name('users.roles-permissions');
        Route::get('users', [UserController::class, 'workspace'])->name('users.index');
        Route::patch('users/roles-permissions/{role}', [UserRolePermissionController::class, 'update'])->name('users.roles-permissions.update');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('erp/administration', [ERPModuleController::class, 'administration'])->name('erp.administration');
        Route::get('erp/admin/erp-settings', [ERPAdministrationMasterDataController::class, 'erpSettings'])->name('erp.admin.erp-settings');
        Route::post('erp/admin/erp-settings', [ERPAdministrationMasterDataController::class, 'updateErpSettings'])->name('erp.admin.erp-settings.update');
        Route::get('erp/admin/maintenance-mode', [ERPAdministrationMasterDataController::class, 'maintenanceMode'])->name('erp.admin.maintenance-mode');
        Route::post('erp/admin/maintenance-mode', [ERPAdministrationMasterDataController::class, 'updateMaintenanceMode'])->name('erp.admin.maintenance-mode.update');
        Route::get('erp/admin/server-monitoring', [ERPAdministrationMasterDataController::class, 'serverMonitoring'])->name('erp.admin.server-monitoring');
        Route::get('erp/admin/document-sequences', [ERPAdministrationMasterDataController::class, 'documentSequences'])->name('erp.admin.document-sequences');
        Route::post('erp/admin/document-sequences', [ERPAdministrationMasterDataController::class, 'storeDocumentSequence'])->name('erp.admin.document-sequences.store');
        Route::patch('erp/admin/document-sequences/{documentSequence}', [ERPAdministrationMasterDataController::class, 'updateDocumentSequence'])->name('erp.admin.document-sequences.update');
        Route::get('erp/admin/payment-methods', [ERPAdministrationMasterDataController::class, 'paymentMethods'])->name('erp.admin.payment-methods');
        Route::post('erp/admin/payment-methods', [ERPAdministrationMasterDataController::class, 'storePaymentMethod'])->name('erp.admin.payment-methods.store');
        Route::patch('erp/admin/payment-methods/{paymentMethod}', [ERPAdministrationMasterDataController::class, 'updatePaymentMethod'])->name('erp.admin.payment-methods.update');
        Route::get('erp/admin/landing-sites', [ERPAdministrationMasterDataController::class, 'landingSites'])->name('erp.admin.landing-sites');
        Route::post('erp/admin/landing-sites', [ERPAdministrationMasterDataController::class, 'storeLandingSite'])->name('erp.admin.landing-sites.store');
        Route::patch('erp/admin/landing-sites/{landingSite}', [ERPAdministrationMasterDataController::class, 'updateLandingSite'])->name('erp.admin.landing-sites.update');
        Route::get('erp/admin/landing-sites/{landingSite}/cms', [ERPAdministrationMasterDataController::class, 'landingSiteCms'])->name('erp.admin.landing-sites.cms');
        Route::post('erp/admin/landing-sites/{landingSite}/cms', [ERPAdministrationMasterDataController::class, 'updateLandingSiteCms'])->name('erp.admin.landing-sites.cms.update');
        Route::get('erp/admin/parser-rules', [ERPAdministrationMasterDataController::class, 'parserRules'])->name('erp.admin.parser-rules');
        Route::post('erp/admin/parser-rules', [ERPAdministrationMasterDataController::class, 'storeParserRule'])->name('erp.admin.parser-rules.store');
        Route::patch('erp/admin/parser-rules/{parserRule}', [ERPAdministrationMasterDataController::class, 'updateParserRule'])->name('erp.admin.parser-rules.update');
        Route::delete('erp/admin/parser-rules/{parserRule}', [ERPAdministrationMasterDataController::class, 'destroyParserRule'])->name('erp.admin.parser-rules.destroy');
        Route::get('erp/admin/system-logs', [ErpSystemLogController::class, 'index'])->name('erp.admin.system-logs.index');
        Route::get('erp/admin/printer-and-label', [ERPAdministrationMasterDataController::class, 'printerAndLabelSettings'])->name('erp.admin.printer-and-label');
        Route::get('erp/admin/data-import', [ERPAdministrationMasterDataController::class, 'dataImport'])->name('erp.admin.data-import');
        Route::get('erp/admin/data-import/products/template', [ERPAdministrationMasterDataController::class, 'downloadMasterProductImportTemplate'])->name('erp.admin.data-import.products.template');
        Route::post('erp/admin/data-import/products', [ERPAdministrationMasterDataController::class, 'importMasterProducts'])->name('erp.admin.data-import.products.store');
        Route::get('erp/admin/data-import/projects/template', [ERPAdministrationMasterDataController::class, 'downloadProjectImportTemplate'])->name('erp.admin.data-import.projects.template');
        Route::post('erp/admin/data-import/projects', [ERPAdministrationMasterDataController::class, 'importProjects'])->name('erp.admin.data-import.projects.store');
        Route::post('erp/admin/data-import/run-seeder', [ERPAdministrationMasterDataController::class, 'runSeeder'])->name('erp.admin.data-import.run-seeder');
        Route::get('erp/admin/master-products/import', [ERPAdministrationMasterDataController::class, 'masterProductImport'])->name('erp.admin.master-products.import');
        Route::get('erp/admin/master-products/import/template', [ERPAdministrationMasterDataController::class, 'downloadMasterProductImportTemplate'])->name('erp.admin.master-products.import.template');
        Route::post('erp/admin/master-products/import', [ERPAdministrationMasterDataController::class, 'importMasterProducts'])->name('erp.admin.master-products.import.store');
        Route::get('erp/admin/thermal-printer', [ERPAdministrationMasterDataController::class, 'thermalPrinter'])->name('erp.admin.thermal-printer');
        Route::post('erp/admin/thermal-printer', [ERPAdministrationMasterDataController::class, 'updateThermalPrinter'])->name('erp.admin.thermal-printer.update');
        Route::post('erp/admin/thermal-printer/test', [ERPAdministrationMasterDataController::class, 'testThermalPrinter'])->name('erp.admin.thermal-printer.test');
        Route::post('erp/admin/thermal-printer/test-pos-receipt', [ERPAdministrationMasterDataController::class, 'testThermalPosReceipt'])->name('erp.admin.thermal-printer.test-pos-receipt');
        Route::post('erp/admin/thermal-printer/preview', [ERPAdministrationMasterDataController::class, 'previewThermalReceipt'])->name('erp.admin.thermal-printer.preview');
        Route::get('erp/admin/label-printer-smb', [ERPAdministrationMasterDataController::class, 'labelPrinterSmb'])->name('erp.admin.label-printer-smb');
        Route::post('erp/admin/label-printer-smb', [ERPAdministrationMasterDataController::class, 'updateLabelPrinterSmb'])->name('erp.admin.label-printer-smb.update');
        Route::post('erp/admin/label-printer-smb/test', [ERPAdministrationMasterDataController::class, 'testLabelPrinterSmb'])->name('erp.admin.label-printer-smb.test');
        Route::get('erp/admin/label-printer-lan', [ERPAdministrationMasterDataController::class, 'labelPrinterLan'])->name('erp.admin.label-printer-lan');
        Route::post('erp/admin/label-printer-lan', [ERPAdministrationMasterDataController::class, 'updateLabelPrinterLan'])->name('erp.admin.label-printer-lan.update');
        Route::post('erp/admin/label-printer-lan/test', [ERPAdministrationMasterDataController::class, 'testLabelPrinterLan'])->name('erp.admin.label-printer-lan.test');
        Route::get('erp/admin/label-profiles', [LabelProfileController::class, 'index'])->name('erp.admin.label-profiles');
        Route::post('erp/admin/label-profiles', [LabelProfileController::class, 'store'])->name('erp.admin.label-profiles.store');
        Route::get('erp/admin/label-profiles/{labelProfile}/simulation', [LabelProfileController::class, 'simulation'])->name('erp.admin.label-profiles.simulation');
        Route::patch('erp/admin/label-profiles/{labelProfile}', [LabelProfileController::class, 'update'])->name('erp.admin.label-profiles.update');
        Route::delete('erp/admin/label-profiles/{labelProfile}', [LabelProfileController::class, 'destroy'])->name('erp.admin.label-profiles.destroy');

        Route::middleware('log.cms.admin.access')->group(function () {
            Route::get('erp/cms', [CmsModuleController::class, 'dashboard'])->name('erp.cms');
            Route::get('erp/cms/sites', [CmsModuleController::class, 'sites'])->name('erp.cms.sites');
            Route::get('erp/cms/media', [CmsMediaController::class, 'index'])->name('erp.cms.media');
        });
        Route::get('erp/cms/media/{cmsMedia}/file', [CmsMediaController::class, 'file'])->name('erp.cms.media.file');
        Route::post('erp/cms/media', [CmsMediaController::class, 'store'])->name('erp.cms.media.store');
        Route::delete('erp/cms/media/{cmsMedia}', [CmsMediaController::class, 'destroy'])->name('erp.cms.media.destroy');
    });
});

require __DIR__.'/auth.php';
