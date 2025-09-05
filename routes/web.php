<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('simple-admin');
});
Route::get('/admin-react/{any}', function () { return view('admin-react'); })->where('any', '.*');

// ===== RESTORED ORIGINAL ADMIN ROUTES =====

// Admin Dashboard Routes
Route::get('/', function () {
    return view('simple-admin');
})->name('dashboard');

Route::get('/admin', function () {
    return view('simple-admin');
})->name('admin.dashboard');

// Referral mint and redirect route
Route::get('/r/{token}', [App\Http\Controllers\ReferralController::class, 'mintAndRedirect']);

Route::get('/dashboard', function () {
    return view('simple-admin');
})->name('admin.home');

// Admin Login
Route::get('/admin/login', function () {
    return view('admin-login');
})->name('admin.login');

// Portal Routes (based on discovered views)
Route::get('/admin/ceo', function () {
    return view('ceo.index');
})->name('admin.ceo');

Route::get('/admin/gm', function () {
    return view('gm-portal.index');
})->name('admin.gm');

Route::get('/admin/accountant', function () {
    return view('accountant-portal.index');
})->name('admin.accountant');

Route::get('/admin/logistics', function () {
    return view('logistics-portal.index');
})->name('admin.logistics');

Route::get('/admin/inventory', function () {
    return view('inventory-portal.index');
})->name('admin.inventory');

Route::get('/admin/telesales', function () {
    return view('telesales.index');
})->name('admin.telesales');

Route::get('/admin/kyc', function () {
    return view('kyc-portal.index');
})->name('admin.kyc');

Route::get('/admin/investor', function () {
    return view('investor.index');
})->name('admin.investor');

// Controller-based routes (if controllers exist)
Route::get('/admin/gm-portal', [App\Http\Controllers\GmPortalController::class, 'index'])->name('gm.portal');
Route::get('/admin/financial', [App\Http\Controllers\FinancialReportingController::class, 'index'])->name('financial.reports');
Route::get('/admin/security', [App\Http\Controllers\SecurityController::class, 'index'])->name('security.dashboard');

// Keep the React route for testing
Route::get('/admin-react/{any}', function () {
    return view('admin-react');
})->where('any', '.*')->name('react.admin');

