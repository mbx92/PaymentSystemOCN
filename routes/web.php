<?php

use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ERPInventoryController;
use App\Http\Controllers\ERPInventoryMasterDataController;
use App\Http\Controllers\ERPAdministrationMasterDataController;
use App\Http\Controllers\ERPMasterProductController;
use App\Http\Controllers\ERPModuleController;
use App\Http\Controllers\ERPPurchasingController;
use App\Http\Controllers\ERPReportingController;
use App\Http\Controllers\ERPSalesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectBudgetController;
use App\Http\Controllers\ProjectRoleController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamDistributionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ErpSystemLogController;
use App\Http\Controllers\ErpChatbotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('erp/chatbot/ask', [ErpChatbotController::class, 'ask'])->name('erp.chatbot.ask');

    // Projects (Admin + Manajer)
    Route::middleware('role:admin|manajer')->group(function () {
        // ERP Module Landing Pages
        Route::get('erp/accounting', [ERPModuleController::class, 'accounting'])->name('erp.accounting');
        Route::get('erp/sales', [ERPModuleController::class, 'sales'])->name('erp.sales');
        Route::get('erp/purchasing', [ERPModuleController::class, 'purchasing'])->name('erp.purchasing');
        Route::get('erp/inventory', [ERPModuleController::class, 'inventory'])->name('erp.inventory');
        Route::get('erp/projects', [ERPModuleController::class, 'projects'])->name('erp.projects');
        Route::get('erp/hr', [ERPModuleController::class, 'hr'])->name('erp.hr');
        Route::get('erp/reporting', [ERPModuleController::class, 'reporting'])->name('erp.reporting');
        Route::get('erp/master-products', [ERPMasterProductController::class, 'index'])->name('erp.master-products.index');
        Route::post('erp/master-products', [ERPMasterProductController::class, 'store'])->name('erp.master-products.store');
        Route::get('erp/master-products/{masterProduct}', [ERPMasterProductController::class, 'show'])->name('erp.master-products.show');
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
        Route::post('erp/inventory/uom-conversions', [ERPInventoryMasterDataController::class, 'storeConversion'])->name('erp.inventory.uom-conversions.store');
        Route::get('erp/inventory/stock-management', [ERPInventoryController::class, 'stockManagement'])->name('erp.inventory.stock-management');
        Route::put('erp/inventory/stock-management/{masterProduct}', [ERPInventoryController::class, 'updateStock'])->name('erp.inventory.stock-management.update');
        Route::get('erp/inventory/stock-opname', [ERPInventoryController::class, 'stockOpname'])->name('erp.inventory.stock-opname');
        Route::post('erp/inventory/stock-opname', [ERPInventoryController::class, 'storeStockOpname'])->name('erp.inventory.stock-opname.store');
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
        Route::delete('projects/{project}/materials/{material}', [ProjectController::class, 'destroyMaterial'])->name('projects.materials.destroy');
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
        Route::get('laporan/general-ledger', [ERPReportingController::class, 'generalLedger'])->name('reports.general-ledger');
        Route::get('laporan/neraca-saldo', [ERPReportingController::class, 'trialBalance'])->name('reports.trial-balance');

        // Export
        Route::get('export/project-profit', [ReportController::class, 'exportProjectProfitExcel'])->name('export.project-profit');
        Route::get('export/bulanan', [ReportController::class, 'exportMonthlyExcel'])->name('export.monthly');
        Route::get('export/anggota', [ReportController::class, 'exportMemberPaymentsExcel'])->name('export.member-payments');
    });

    // User Management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('erp/administration', [ERPModuleController::class, 'administration'])->name('erp.administration');
        Route::get('erp/admin/document-sequences', [ERPAdministrationMasterDataController::class, 'documentSequences'])->name('erp.admin.document-sequences');
        Route::post('erp/admin/document-sequences', [ERPAdministrationMasterDataController::class, 'storeDocumentSequence'])->name('erp.admin.document-sequences.store');
        Route::patch('erp/admin/document-sequences/{documentSequence}', [ERPAdministrationMasterDataController::class, 'updateDocumentSequence'])->name('erp.admin.document-sequences.update');
        Route::get('erp/admin/payment-methods', [ERPAdministrationMasterDataController::class, 'paymentMethods'])->name('erp.admin.payment-methods');
        Route::post('erp/admin/payment-methods', [ERPAdministrationMasterDataController::class, 'storePaymentMethod'])->name('erp.admin.payment-methods.store');
        Route::patch('erp/admin/payment-methods/{paymentMethod}', [ERPAdministrationMasterDataController::class, 'updatePaymentMethod'])->name('erp.admin.payment-methods.update');
        Route::get('erp/admin/parser-rules', [ERPAdministrationMasterDataController::class, 'parserRules'])->name('erp.admin.parser-rules');
        Route::post('erp/admin/parser-rules', [ERPAdministrationMasterDataController::class, 'storeParserRule'])->name('erp.admin.parser-rules.store');
        Route::patch('erp/admin/parser-rules/{parserRule}', [ERPAdministrationMasterDataController::class, 'updateParserRule'])->name('erp.admin.parser-rules.update');
        Route::post('erp/admin/parser-rules/test', [ERPAdministrationMasterDataController::class, 'testParserRule'])->name('erp.admin.parser-rules.test');
        Route::get('erp/admin/system-logs', [ErpSystemLogController::class, 'index'])->name('erp.admin.system-logs.index');
    });
});

require __DIR__.'/auth.php';
