<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollectionController;
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
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Vite build assets served through PHP.
// The production host's LiteSpeed static layer reads a frozen filesystem
// snapshot (stale CageFS mount): files added/changed after deploy 404 or serve
// stale bytes. PHP sees the live disk, so requests for asset filenames unknown
// to LiteSpeed fall through to index.php and this route streams the real file.
// Filenames are content-hashed, so aggressive caching is safe. Remove once the
// host remounts CageFS / restarts LiteSpeed.
Route::get('/build/{path}', function (string $path) {
    $base = realpath(public_path('build'));
    $full = realpath(public_path('build/' . $path));
    abort_unless(
        $base && $full && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full),
        404
    );
    $types = [
        'js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json',
        'map' => 'application/json', 'woff2' => 'font/woff2', 'woff' => 'font/woff',
        'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    ];
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

    return response()->file($full, [
        'Content-Type' => $types[$ext] ?? 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '[A-Za-z0-9_\-./]+');

// Same LiteSpeed-stale-snapshot fallback for the private client report's static
// assets (public/report). PHP files are deliberately excluded — they must be
// executed by the server, never streamed as source.
Route::get('/report/{path}', function (string $path) {
    $base = realpath(public_path('report'));
    $full = realpath(public_path('report/' . $path));
    abort_unless(
        $base && $full && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)
            && !str_ends_with(strtolower($full), '.php'),
        404
    );
    $types = [
        'html' => 'text/html', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'css' => 'text/css', 'js' => 'application/javascript',
        'pdf' => 'application/pdf', 'zip' => 'application/zip', 'csv' => 'text/csv',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'gif' => 'image/gif',
    ];
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $cache = $ext === 'html' ? 'no-cache' : 'public, max-age=86400';

    return response()->file($full, [
        'Content-Type' => $types[$ext] ?? 'application/octet-stream',
        'Cache-Control' => $cache,
        'X-Robots-Tag' => 'noindex, nofollow',
    ]);
})->where('path', '[A-Za-z0-9_\-./]+');

// Public shop
Route::get('/', [ShopController::class, 'index'])->name('shop');
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::post('/shop/checkout', [ShopController::class, 'checkout'])->middleware('throttle:30,1')->name('shop.checkout');
Route::post('/shop/place-order', [ShopController::class, 'placeOrder'])->middleware('throttle:10,1')->name('shop.place-order');
Route::get('/shop/confirmation/{invoice}', [ShopController::class, 'confirmation'])->name('shop.confirmation');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile (all authenticated users)
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

    // Fabrics (Admin, Manager)
    Route::middleware('role:Admin|Manager')->group(function () {
        Route::get('/fabrics', [FabricController::class, 'index'])->name('fabrics.index');
        Route::post('/fabrics', [FabricController::class, 'store'])->name('fabrics.store');
        Route::put('/fabrics/{fabric}', [FabricController::class, 'update'])->name('fabrics.update');
        Route::delete('/fabrics/{fabric}', [FabricController::class, 'destroy'])->name('fabrics.destroy');
    });

    // Collections (off-the-shelf items)
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::middleware('role:Admin|Manager')->group(function () {
        Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
        Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
        Route::post('/collections/{collection}/adjust-stock', [CollectionController::class, 'adjustStock'])->name('collections.adjust-stock');
        Route::post('/collection-categories', [CollectionController::class, 'storeCategory'])->name('collection-categories.store');
        Route::put('/collection-categories/{category}', [CollectionController::class, 'updateCategory'])->name('collection-categories.update');
        Route::delete('/collection-categories/{category}', [CollectionController::class, 'destroyCategory'])->name('collection-categories.destroy');
    });

    // Expenses (Admin, Manager)
    Route::middleware('role:Admin|Manager')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // POS (Admin, Manager, Secretary, Cashier)
    Route::middleware('role:Admin|Manager|Secretary|Cashier')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
        Route::get('/pos/{invoice}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/pos/client/{client}/orders', [PosController::class, 'clientOrders'])->name('pos.client-orders');
    });

    // Inventory (Admin, Manager)
    Route::middleware('role:Admin|Manager')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
        Route::post('/inventory/reserve', [InventoryController::class, 'reserve'])->name('inventory.reserve');
        Route::post('/inventory/release', [InventoryController::class, 'release'])->name('inventory.release');
        Route::get('/inventory/movements/{type}/{id}', [InventoryController::class, 'movements'])->name('inventory.movements');
    });

    // Reports (Admin, Manager)
    Route::get('/reports', [ReportController::class, 'index'])->middleware('role:Admin|Manager')->name('reports.index');

    // User Management (Admin only)
    Route::middleware('role:Admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/roles', [UserController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [UserController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [UserController::class, 'destroyRole'])->name('roles.destroy');
    });

    // Settings (Admin only)
    Route::middleware('role:Admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings/company', [SettingController::class, 'updateCompany'])->name('settings.company.update');
        Route::post('/settings/company/logo', [SettingController::class, 'uploadLogo'])->name('settings.company.logo');
        Route::delete('/settings/company/logo', [SettingController::class, 'removeLogo'])->name('settings.company.logo.remove');
        Route::put('/settings/website', [SettingController::class, 'updateWebsite'])->name('settings.website.update');
        Route::post('/settings/shop-toggle/{collection}', [SettingController::class, 'toggleShopItem'])->name('settings.shop.toggle');
        Route::post('/settings/shop-bulk-toggle', [SettingController::class, 'bulkToggleShop'])->name('settings.shop.bulk-toggle');
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
});

require __DIR__.'/auth.php';
