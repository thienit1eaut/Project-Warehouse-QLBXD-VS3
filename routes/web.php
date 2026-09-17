<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaFolderController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\StockController;
use Illuminate\Support\Facades\Route;

// ── Trang chủ redirect ──────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Auth (guest only) ───────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [LoginController::class, 'create'])->name('login');
    Route::post('/login',    [LoginController::class, 'store'])->name('login.store');
    Route::get('/register',  [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

// ── Logout ──────────────────────────────────────────────────────────────────
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ── Admin ───────────────────────────────────────────────────────────────────
// Middleware stack: auth -> active -> (permission per-route)
// 'active' đảm bảo user không bị deactivate sau khi đã login vẫn tiếp tục request được.
Route::middleware(['auth', 'active'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ── User Management (legacy: middleware 'admin' — role-based cũ) ────────
    // TODO Phase N: migrate sang permission:user-management.view / .create / ...
    // khi User Management module được thêm vào permission catalog.
    Route::middleware('admin')->group(function () {
        Route::get('/users',               [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create',        [UserController::class, 'create'])->name('users.create');
        Route::post('/users',              [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit',   [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}',        [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}',     [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ── Category/Brand/Supplier/Unit (legacy: middleware 'manager') ─────────
    // TODO Phase N: migrate từng module sang permission:category.view / .create / ...
    Route::middleware('manager')->group(function () {
        Route::get('/categories',                    [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create',             [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories',                   [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit',    [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}',         [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}',      [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/brands',                        [BrandController::class, 'index'])->name('brands.index');
        Route::get('/brands/create',                 [BrandController::class, 'create'])->name('brands.create');
        Route::post('/brands',                       [BrandController::class, 'store'])->name('brands.store');
        Route::get('/brands/{brand}/edit',            [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('/brands/{brand}',                 [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}',              [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('/suppliers',                     [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create',              [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers',                    [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit',     [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}',          [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}',       [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/units',                         [UnitController::class, 'index'])->name('units.index');
        Route::get('/units/create',                  [UnitController::class, 'create'])->name('units.create');
        Route::post('/units',                        [UnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}/edit',             [UnitController::class, 'edit'])->name('units.edit');
        Route::put('/units/{unit}',                  [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}',               [UnitController::class, 'destroy'])->name('units.destroy');

        // ── Product (Master Data Phase 1) ────────────────────────────────
        Route::get('/products',                    [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create',              [ProductController::class, 'create'])->name('products.create');
        Route::post('/products',                    [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}',           [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit',      [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}',           [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}',        [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // ── Media Library — permission-based (không dùng role slug) ────────────
    //
    // Mapping route -> permission:
    // GET  index                 -> media.view
    // GET  trash                 -> media.restore
    // GET  upload (create page)  -> media.create
    // POST store                 -> media.create
    // GET  show (JSON)           -> media.view
    // GET  {media}/edit          -> media.update
    // PUT  update                -> media.update
    // DELETE destroy             -> media.delete
    // POST restore               -> media.restore
    // DELETE force-delete        -> media.force-delete
    // POST/DELETE/PUT folder ops -> media.manage-folder
    Route::prefix('media')->name('media.')->group(function () {
        Route::get('/', [MediaController::class, 'index'])
            ->middleware('permission:media.view')->name('index');

        Route::post('/', [MediaController::class, 'store'])
            ->middleware('permission:media.create')->name('store');

        // BẮT BUỘC đặt TRƯỚC GET /{media} — static routes '/trash' và '/upload'
        // là literal segment, nếu đặt SAU '/{media}' thì Laravel sẽ match
        // '/{media}' trước (vì cùng là 1-segment pattern, khai báo trước thắng),
        // biến "trash"/"upload" thành giá trị {media} và gọi nhầm show().
        Route::get('/trash', [MediaController::class, 'trash'])
            ->middleware('permission:media.restore')->name('trash');

        Route::get('/upload', [MediaController::class, 'create'])
            ->middleware('permission:media.create')->name('create');

        // '/bulk' là literal 1-segment — cùng lý do '/trash' và '/upload' ở
        // trên, BẮT BUỘC đặt trước '/{media}' (cũng 1-segment) để không bị
        // Laravel hiểu nhầm 'bulk' là giá trị {media}.
        Route::delete('/bulk', [MediaController::class, 'bulkDestroy'])
            ->middleware('permission:media.delete')->name('bulk-destroy');

        // 2-segment — không conflict với '/{media}' (1-segment) dù đặt ở đâu,
        // nhưng giữ gần nhóm route tĩnh khác cho dễ đọc.
        Route::post('/bulk/add-to-folder', [MediaController::class, 'bulkAddToFolder'])
            ->middleware('permission:media.manage-folder')->name('bulk-add-to-folder');

        // '/picker' là literal 1-segment — cùng lý do các route tĩnh khác,
        // BẮT BUỘC đặt trước '/{media}'. Dùng bởi MediaPicker.vue (module
        // khác như Brand nhúng chọn ảnh) — chỉ trả Media type=image.
        Route::get('/picker', [MediaController::class, 'picker'])
            ->middleware('permission:media.view')->name('picker');

        Route::get('/{media}', [MediaController::class, 'show'])
            ->middleware('permission:media.view')->name('show');

        // 2-segment route — không conflict với '/{media}' (1-segment) dù khai
        // báo trước hay sau, vì Laravel phân biệt theo số lượng segment.
        Route::get('/{media}/edit', [MediaController::class, 'edit'])
            ->middleware('permission:media.update')->name('edit');

        Route::put('/{media}', [MediaController::class, 'update'])
            ->middleware('permission:media.update')->name('update');

        Route::delete('/{media}', [MediaController::class, 'destroy'])
            ->middleware('permission:media.delete')->name('destroy');

        // ->withTrashed() bắt buộc — record đang trashed không lọt qua
        // route model binding mặc định (SoftDeletes global scope).
        Route::post('/{media}/restore', [MediaController::class, 'restore'])
            ->withTrashed()
            ->middleware('permission:media.restore')->name('restore');

        Route::delete('/{media}/force', [MediaController::class, 'forceDelete'])
            ->withTrashed()
            ->middleware('permission:media.force-delete')->name('force-delete');

        // Folder relationship actions
        Route::post('/{media}/folders/{folder}', [MediaController::class, 'addToFolder'])
            ->middleware('permission:media.manage-folder')->name('add-to-folder');

        Route::delete('/{media}/folders/{folder}', [MediaController::class, 'removeFromFolder'])
            ->middleware('permission:media.manage-folder')->name('remove-from-folder');

        Route::put('/{media}/folders/{from}/move/{to}', [MediaController::class, 'moveToFolder'])
            ->middleware('permission:media.manage-folder')->name('move-to-folder');
    });

    // ── Media Folder ─────────────────────────────────────────────────────────
    //
    // GET  index               -> media-folder.view
    // GET  trash                -> media-folder.restore
    // GET  create (form page)   -> media-folder.create
    // POST store                -> media-folder.create
    // GET  {folder}/children    -> media-folder.view
    // GET  {folder}/edit        -> media-folder.update
    // PUT  update (rename+move) -> media-folder.update
    // DELETE destroy            -> media-folder.delete
    // POST restore               -> media-folder.restore
    // DELETE force-delete        -> media-folder.force-delete
    Route::prefix('media-folders')->name('media-folders.')->group(function () {
        Route::get('/', [MediaFolderController::class, 'index'])
            ->middleware('permission:media-folder.view')->name('index');

        Route::post('/', [MediaFolderController::class, 'store'])
            ->middleware('permission:media-folder.create')->name('store');

        // BẮT BUỘC đặt TRƯỚC PUT/DELETE /{folder} — cùng lý do route Media
        // ở trên: '/trash' và '/create' là literal 1-segment, phải thắng
        // trước dynamic '/{folder}' (cũng 1-segment).
        Route::get('/trash', [MediaFolderController::class, 'trash'])
            ->middleware('permission:media-folder.restore')->name('trash');

        Route::get('/create', [MediaFolderController::class, 'create'])
            ->middleware('permission:media-folder.create')->name('create');

        // 2-segment routes — không conflict với '/{folder}' (1-segment).
        Route::get('/{folder}/children', [MediaFolderController::class, 'children'])
            ->middleware('permission:media-folder.view')->name('children');

        Route::get('/{folder}/edit', [MediaFolderController::class, 'edit'])
            ->middleware('permission:media-folder.update')->name('edit');

        Route::put('/{folder}', [MediaFolderController::class, 'update'])
            ->middleware('permission:media-folder.update')->name('update');

        Route::delete('/{folder}', [MediaFolderController::class, 'destroy'])
            ->middleware('permission:media-folder.delete')->name('destroy');

        Route::post('/{folder}/restore', [MediaFolderController::class, 'restore'])
            ->withTrashed()
            ->middleware('permission:media-folder.restore')->name('restore');

        Route::delete('/{folder}/force', [MediaFolderController::class, 'forceDelete'])
            ->withTrashed()
            ->middleware('permission:media-folder.force-delete')->name('force-delete');
    });

    // ── Warehouses ─────────────────────────────────────────────────────────

    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])
            ->middleware('permission:warehouses.view')->name('index');

        Route::get('/create', [WarehouseController::class, 'create'])
            ->middleware('permission:warehouses.create')->name('create');

        Route::post('/', [WarehouseController::class, 'store'])
            ->middleware('permission:warehouses.create')->name('store');

        Route::get('/{warehouse}', [WarehouseController::class, 'show'])
            ->middleware('permission:warehouses.view')->name('show');

        Route::get('/{warehouse}/edit', [WarehouseController::class, 'edit'])
            ->middleware('permission:warehouses.update')->name('edit');

        Route::put('/{warehouse}', [WarehouseController::class, 'update'])
            ->middleware('permission:warehouses.update')->name('update');

        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])
            ->middleware('permission:warehouses.delete')->name('destroy');
    });

    // ── Stock ─────────────────────────────────────────────────────────

    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'index'])
            ->middleware('permission:stock.view')->name('index');

        Route::get('/{stock}', [StockController::class, 'show'])
            ->middleware('permission:stock.view')->name('show');
    });
});