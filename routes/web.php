<?php

use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamDistributionController;
use App\Http\Controllers\UserController;
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
        Route::resource('projects', ProjectController::class);

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
    });
});

require __DIR__ . '/auth.php';
