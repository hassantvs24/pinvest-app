<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Owner\CommissionController as OwnerCommissionController;
use App\Http\Controllers\Owner\EntryController as OwnerEntryController;
use App\Http\Controllers\Owner\InvestmentController as OwnerInvestmentController;
use App\Http\Controllers\Owner\MasterController as OwnerMasterController;
use App\Http\Controllers\Owner\PartnerController as OwnerPartnerController;
use App\Http\Controllers\Owner\PayoutController as OwnerPayoutController;
use App\Http\Controllers\Owner\ProductionController as OwnerProductionController;
use App\Http\Controllers\Owner\ReportController as OwnerReportController;
use App\Http\Controllers\Owner\WithdrawalController as OwnerWithdrawalController;
use App\Http\Controllers\PartnerCommissionController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(
        auth()->check() ? 'dashboard' : 'login'
    );
});

// ---------------------------------------------------------------------------
// Guest routes
// ---------------------------------------------------------------------------
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// ---------------------------------------------------------------------------
// Authenticated routes
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Language picker (reachable even before a language is chosen).
    Route::get('/select-language', [LanguageController::class, 'show'])->name('language.show');
    Route::post('/select-language', [LanguageController::class, 'store'])->name('language.store');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Partner commissions + payout request.
    Route::get('/my-commissions', [PartnerCommissionController::class, 'index'])->name('commissions.index');
    Route::post('/my-commissions/request', [PartnerCommissionController::class, 'requestPayout'])->name('commissions.request');

    // Partner production runs (pending until the owner confirms).
    Route::get('/productions', [ProductionController::class, 'index'])->name('productions.index');
    Route::post('/productions', [ProductionController::class, 'store'])->name('productions.store');

    // Partner entries (own only — scoped by user_id in the controller).
    Route::get('/entries/{type}', [EntryController::class, 'index'])->name('entries.index');
    Route::get('/entries/{type}/create', [EntryController::class, 'create'])->name('entries.create');
    Route::post('/entries/{type}', [EntryController::class, 'store'])->name('entries.store');

    // Owner-only area.
    Route::prefix('owner')->name('owner.')->middleware('owner')->group(function (): void {
        Route::get('/reports', [OwnerReportController::class, 'index'])->name('reports.index');

        // Production runs (raw materials -> finished goods).
        Route::get('/productions', [OwnerProductionController::class, 'index'])->name('productions.index');
        Route::post('/productions', [OwnerProductionController::class, 'store'])->name('productions.store');
        Route::patch('/productions/{production}/confirm', [OwnerProductionController::class, 'confirm'])->name('productions.confirm');
        Route::patch('/productions/{production}/reject', [OwnerProductionController::class, 'reject'])->name('productions.reject');
        Route::delete('/productions/{production}', [OwnerProductionController::class, 'destroy'])->name('productions.destroy');
        Route::post('/allowances', [OwnerPartnerController::class, 'storeAllowance'])->name('allowances.store');
        Route::delete('/allowances/{allowance}', [OwnerPartnerController::class, 'destroyAllowance'])->name('allowances.destroy');

        Route::get('/entries', [OwnerEntryController::class, 'index'])->name('entries.index');
        Route::patch('/entries/{type}/{entry}/confirm', [OwnerEntryController::class, 'confirm'])->name('entries.confirm');
        Route::patch('/entries/{type}/{entry}/reject', [OwnerEntryController::class, 'reject'])->name('entries.reject');
        Route::patch('/entries/{type}/{entry}', [OwnerEntryController::class, 'update'])->name('entries.update');
        Route::delete('/entries/{type}/{entry}', [OwnerEntryController::class, 'destroy'])->name('entries.destroy');

        Route::get('/masters', [OwnerMasterController::class, 'index'])->name('masters.index');
        Route::post('/masters/{group}', [OwnerMasterController::class, 'store'])->name('masters.store');
        Route::patch('/masters/{group}/{id}', [OwnerMasterController::class, 'update'])->name('masters.update');
        Route::patch('/masters/{group}/{id}/toggle', [OwnerMasterController::class, 'toggle'])->name('masters.toggle');
        Route::delete('/masters/{group}/{id}', [OwnerMasterController::class, 'destroy'])->name('masters.destroy');

        Route::get('/partners', [OwnerPartnerController::class, 'index'])->name('partners.index');
        Route::get('/partners/create', [OwnerPartnerController::class, 'create'])->name('partners.create');
        Route::post('/partners', [OwnerPartnerController::class, 'store'])->name('partners.store');
        Route::get('/partners/{partner}/edit', [OwnerPartnerController::class, 'edit'])->name('partners.edit');
        Route::put('/partners/{partner}', [OwnerPartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [OwnerPartnerController::class, 'destroy'])->name('partners.destroy');

        Route::get('/investments', [OwnerInvestmentController::class, 'index'])->name('investments.index');
        Route::post('/investments', [OwnerInvestmentController::class, 'store'])->name('investments.store');

        Route::get('/payouts', [OwnerPayoutController::class, 'index'])->name('payouts.index');

        Route::get('/commissions', [OwnerCommissionController::class, 'index'])->name('commissions.index');
        Route::post('/commissions/open', [OwnerCommissionController::class, 'openPeriod'])->name('commissions.open');
        Route::get('/commissions/close', [OwnerCommissionController::class, 'closePreview'])->name('commissions.close_preview');
        Route::post('/commissions/close', [OwnerCommissionController::class, 'closePeriod'])->name('commissions.close');
        Route::patch('/commissions/requests/{request}/approve', [OwnerCommissionController::class, 'approveRequest'])->name('commissions.approve');
        Route::patch('/commissions/requests/{request}/reject', [OwnerCommissionController::class, 'rejectRequest'])->name('commissions.reject');

        Route::get('/withdrawals', [OwnerWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/withdrawals', [OwnerWithdrawalController::class, 'store'])->name('withdrawals.store');
    });
});
