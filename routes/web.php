<?php

use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ERPInventoryController;
use App\Http\Controllers\ERPInventoryMasterDataController;
use App\Http\Controllers\ERPMasterProductController;
use App\Http\Controllers\ERPModuleController;
use App\Http\Controllers\ERPPurchasingController;
use App\Http\Controllers\ERPReportingController;
use App\Http\Controllers\ERPSalesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamDistributionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ErpSystemLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
        Route::delete('erp/master-products/{masterProduct}', [ERPMasterProductController::class, 'destroy'])->name('erp.master-products.destroy');
        Route::get('erp/inventory/categories', [ERPInventoryMasterDataController::class, 'categories'])->name('erp.inventory.categories');
        Route::post('erp/inventory/categories', [ERPInventoryMasterDataController::class, 'storeCategory'])->name('erp.inventory.categories.store');
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
        Route::get('erp/sales/project-invoices', [ERPSalesController::class, 'projectInvoices'])->name('erp.sales.project-invoices');

        Route::resource('projects', ProjectController::class);
        Route::post('projects/{project}/materials', [ProjectController::class, 'storeMaterial'])->name('projects.materials.store');
        Route::delete('projects/{project}/materials/{material}', [ProjectController::class, 'destroyMaterial'])->name('projects.materials.destroy');

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
        Route::get('erp/admin/system-logs', [ErpSystemLogController::class, 'index'])->name('erp.admin.system-logs.index');
    });
});

require __DIR__.'/auth.php';
