<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FabricController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GarmentTypeController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clients
    Route::resource('clients', ClientController::class)->except(['create', 'edit']);
    Route::get('/clients/{client}/statement', [ClientController::class, 'statement'])->name('clients.statement');
    Route::get('/clients/{client}/statement/pdf', [ClientController::class, 'statementPdf'])->name('clients.statement.pdf');

    // Contacts
    Route::post('/clients/{client}/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Measurements
    Route::post('/contacts/{contact}/measurements', [MeasurementController::class, 'store'])->name('measurements.store');
    Route::put('/measurements/{measurement}', [MeasurementController::class, 'update'])->name('measurements.update');
    Route::delete('/measurements/{measurement}', [MeasurementController::class, 'destroy'])->name('measurements.destroy');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    // Payments
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');

    // Fabrics
    Route::get('/fabrics', [FabricController::class, 'index'])->name('fabrics.index');
    Route::post('/fabrics', [FabricController::class, 'store'])->name('fabrics.store');
    Route::put('/fabrics/{fabric}', [FabricController::class, 'update'])->name('fabrics.update');
    Route::delete('/fabrics/{fabric}', [FabricController::class, 'destroy'])->name('fabrics.destroy');

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/company', [SettingController::class, 'updateCompany'])->name('settings.company.update');
    Route::post('/settings/company/logo', [SettingController::class, 'uploadLogo'])->name('settings.company.logo');
    Route::delete('/settings/company/logo', [SettingController::class, 'removeLogo'])->name('settings.company.logo.remove');
    Route::post('/settings/users', [SettingController::class, 'storeUser'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [SettingController::class, 'updateUser'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [SettingController::class, 'destroyUser'])->name('settings.users.destroy');

    // Garment Types
    Route::post('/settings/garment-types', [GarmentTypeController::class, 'store'])->name('garment-types.store');
    Route::put('/settings/garment-types/{garmentType}', [GarmentTypeController::class, 'update'])->name('garment-types.update');
    Route::delete('/settings/garment-types/{garmentType}', [GarmentTypeController::class, 'destroy'])->name('garment-types.destroy');
    Route::post('/settings/garment-types/reorder', [GarmentTypeController::class, 'reorder'])->name('garment-types.reorder');
    Route::post('/settings/garment-types/{garmentType}/fields', [GarmentTypeController::class, 'storeField'])->name('garment-type-fields.store');
    Route::post('/settings/garment-types/{garmentType}/fields/reorder', [GarmentTypeController::class, 'reorderFields'])->name('garment-type-fields.reorder');
    Route::put('/settings/garment-type-fields/{field}', [GarmentTypeController::class, 'updateField'])->name('garment-type-fields.update');
    Route::delete('/settings/garment-type-fields/{field}', [GarmentTypeController::class, 'destroyField'])->name('garment-type-fields.destroy');
});

require __DIR__.'/auth.php';
