<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\CollectionController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\PromotionController;
use App\Http\Controllers\Dashboard\SalesController;
use App\Http\Middleware\EnsureCasAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::get('/Login', [LoginController::class, 'callback'])->name('login.callback');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(EnsureCasAuthenticated::class)->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/dashboard-api/collections', [CollectionController::class, 'index'])
        ->name('dashboard-api.collections.index');
    Route::post('/dashboard-api/collections', [CollectionController::class, 'store'])
        ->name('dashboard-api.collections.store');
    Route::post('/dashboard-api/collections/{collection}', [CollectionController::class, 'update'])
        ->name('dashboard-api.collections.update');
    Route::get('/dashboard-api/collections/{collection}/assets', [CollectionController::class, 'assets'])
        ->name('dashboard-api.collections.assets.index');
    Route::post('/dashboard-api/collections/{collection}/assets', [CollectionController::class, 'storeAsset'])
        ->name('dashboard-api.collections.assets.store');
    Route::get('/dashboard-api/promotions', [PromotionController::class, 'index'])
        ->name('dashboard-api.promotions.index');
    Route::post('/dashboard-api/promotions', [PromotionController::class, 'store'])
        ->name('dashboard-api.promotions.store');
    Route::post('/dashboard-api/promotions/{promotion}/schedule', [PromotionController::class, 'updateSchedule'])
        ->name('dashboard-api.promotions.schedule.update');
    Route::get('/dashboard-api/promotions/{promotion}/assets', [PromotionController::class, 'assets'])
        ->name('dashboard-api.promotions.assets.index');
    Route::post('/dashboard-api/promotions/{promotion}/assets', [PromotionController::class, 'storeAsset'])
        ->name('dashboard-api.promotions.assets.store');
    Route::post('/dashboard-api/promotions/assets/{asset}', [PromotionController::class, 'updateAsset'])
        ->name('dashboard-api.promotions.assets.update');
    Route::delete('/dashboard-api/promotions/assets/{asset}', [PromotionController::class, 'destroyAsset'])
        ->name('dashboard-api.promotions.assets.destroy');
    Route::post('/dashboard-api/promotions/{promotion}/header', [PromotionController::class, 'updateHeader'])
        ->name('dashboard-api.promotions.header.update');
    Route::get('/dashboard-api/sales/kpi', [SalesController::class, 'kpi'])
        ->name('dashboard-api.sales.kpi');
    Route::get('/dashboard-api/sales/orders', [SalesController::class, 'orders'])
        ->name('dashboard-api.sales.orders');
    Route::get('/dashboard-api/orders/reference', [OrderController::class, 'showByReference'])
        ->name('dashboard-api.orders.reference');
    Route::get('/dashboard-api/orders/search', [OrderController::class, 'search'])
        ->name('dashboard-api.orders.search');
    Route::get('/dashboard-api/orders/payment-attempts', [OrderController::class, 'paymentAttempts'])
        ->name('dashboard-api.orders.payment-attempts');
    Route::get('/dashboard-api/orders/refunds', [OrderController::class, 'refunds'])
        ->name('dashboard-api.orders.refunds');
    Route::get('/dashboard-api/orders/product', [OrderController::class, 'product'])
        ->name('dashboard-api.orders.product');
    Route::post('/dashboard-api/orders/lines/{line}', [OrderController::class, 'updateLine'])
        ->name('dashboard-api.orders.lines.update');
    Route::post('/dashboard-api/orders/process', [OrderController::class, 'process'])
        ->name('dashboard-api.orders.process');
    Route::post('/dashboard-api/orders/route', [OrderController::class, 'markInRoute'])
        ->name('dashboard-api.orders.route');
    Route::post('/dashboard-api/orders/deliver', [OrderController::class, 'deliver'])
        ->name('dashboard-api.orders.deliver');

    Route::get('/colecciones', fn () => Inertia::render('Collections/Index'))
        ->name('collections.index');
    Route::get('/promociones', fn () => Inertia::render('Promotions/Index'))
        ->name('promotions.index');
    Route::get('/venta', fn () => Inertia::render('Sales/Index'))
        ->name('sales.index');
    Route::get('/pedidos/pendientes', fn () => Inertia::render('Orders/Pending'))
        ->name('orders.pending');
    Route::get('/pedidos/procesados', fn () => Inertia::render('Orders/Processed'))
        ->name('orders.processed');
    Route::get('/pedidos/devoluciones', fn () => Inertia::render('Orders/Refunds'))
        ->name('orders.refunds');
    Route::get('/pedidos/busqueda', fn () => Inertia::render('Orders/Search'))
        ->name('orders.search');
    Route::get('/pedidos/consulta', fn (Request $request) => Inertia::render('Orders/Reference', [
        'initialCountry' => $request->string('country')->toString(),
        'initialReference' => $request->string('id')->toString(),
    ]))->name('orders.reference');

    foreach ([
        '/citas' => 'Citas',
        '/cupones/mantenimiento' => 'Cupones / Mantenimiento',
        '/cupones/reportes' => 'Cupones / Reportes',
        '/pedidos/gestiones' => 'Pedidos / Gestiones',
        '/reportes/catalogo' => 'Reportes / Catalogo',
        '/reportes/suscriptores' => 'Reportes / Suscriptores',
        '/reportes/im/venta' => 'Reportes / IM Venta',
        '/reportes/corte-virtual' => 'Reportes / Corte Virtual',
        '/reportes/articulos-pendientes' => 'Reportes / Articulos pendientes',
        '/reportes/articulos-pendientes-pedido' => 'Reportes / Articulos pendientes por pedido',
        '/reportes/contabilidad/venta-general' => 'Reportes / Contabilidad',
        '/reportes/contabilidad/venta-general-2' => 'Reportes / Contabilidad 2',
        '/reportes/contabilidad/venta-general-3' => 'Reportes / Contabilidad 3',
        '/productos/categorias' => 'Productos / Categorias',
        '/productos/catalogo' => 'Productos / Maestro',
        '/productos/pais' => 'Productos / Por pais',
        '/configuracion/log' => 'Configuracion / LOG',
        '/configuracion/slides' => 'Configuracion / Slides',
        '/configuracion/imagenes' => 'Configuracion / Imagenes',
    ] as $uri => $title) {
        Route::get($uri, fn () => Inertia::render('Modules/Placeholder', [
            'title' => $title,
        ]));
    }
});
