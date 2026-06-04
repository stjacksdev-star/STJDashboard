<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\AccountingReportController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\ClaimController;
use App\Http\Controllers\Dashboard\CollectionController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\PromotionController;
use App\Http\Controllers\Dashboard\ProductCategoryController;
use App\Http\Controllers\Dashboard\ProductCountryController;
use App\Http\Controllers\Dashboard\ProductMasterController;
use App\Http\Controllers\Dashboard\SalesController;
use App\Http\Controllers\Dashboard\StoreReportController;
use App\Http\Controllers\Dashboard\SubscriberController;
use App\Http\Controllers\Dashboard\UserCountryAccessController;
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
    Route::get('/dashboard-api/product-categories', [ProductCategoryController::class, 'index'])
        ->name('dashboard-api.product-categories.index');
    Route::post('/dashboard-api/product-categories', [ProductCategoryController::class, 'store'])
        ->name('dashboard-api.product-categories.store');
    Route::post('/dashboard-api/product-categories/{category}', [ProductCategoryController::class, 'update'])
        ->name('dashboard-api.product-categories.update');
    Route::delete('/dashboard-api/product-categories/{category}', [ProductCategoryController::class, 'destroy'])
        ->name('dashboard-api.product-categories.destroy');
    Route::get('/dashboard-api/products/master', [ProductMasterController::class, 'index'])
        ->name('dashboard-api.products.master.index');
    Route::post('/dashboard-api/products/master/import', [ProductMasterController::class, 'import'])
        ->name('dashboard-api.products.master.import');
    Route::post('/dashboard-api/products/master/photos/import', [ProductMasterController::class, 'importPhotos'])
        ->name('dashboard-api.products.master.photos.import');
    Route::get('/dashboard-api/products/master/{product}', [ProductMasterController::class, 'show'])
        ->name('dashboard-api.products.master.show');
    Route::get('/dashboard-api/products/master/{product}/photos', [ProductMasterController::class, 'photos'])
        ->name('dashboard-api.products.master.photos');
    Route::get('/dashboard-api/products/master/{product}/countries', [ProductMasterController::class, 'countries'])
        ->name('dashboard-api.products.master.countries');
    Route::get('/dashboard-api/products/country/countries', [ProductCountryController::class, 'countries'])
        ->name('dashboard-api.products.country.countries');
    Route::post('/dashboard-api/products/country/import', [ProductCountryController::class, 'import'])
        ->name('dashboard-api.products.country.import');
    Route::post('/dashboard-api/products/country/deactivate', [ProductCountryController::class, 'deactivate'])
        ->name('dashboard-api.products.country.deactivate');
    Route::get('/dashboard-api/sales/kpi', [SalesController::class, 'kpi'])
        ->name('dashboard-api.sales.kpi');
    Route::get('/dashboard-api/sales/catalog', [SalesController::class, 'catalog'])
        ->name('dashboard-api.sales.catalog');
    Route::get('/dashboard-api/sales/regional-chart', [SalesController::class, 'regionalChart'])
        ->name('dashboard-api.sales.regional-chart');
    Route::get('/dashboard-api/sales/conversion', [SalesController::class, 'conversion'])
        ->name('dashboard-api.sales.conversion');
    Route::get('/dashboard-api/sales/visits', [SalesController::class, 'visits'])
        ->name('dashboard-api.sales.visits');
    Route::get('/dashboard-api/sales/satisfaction', [SalesController::class, 'satisfaction'])
        ->name('dashboard-api.sales.satisfaction');
    Route::get('/dashboard-api/sales/categories', [SalesController::class, 'categories'])
        ->name('dashboard-api.sales.categories');
    Route::get('/dashboard-api/sales/segments', [SalesController::class, 'segments'])
        ->name('dashboard-api.sales.segments');
    Route::get('/dashboard-api/sales/payment-forms', [SalesController::class, 'paymentForms'])
        ->name('dashboard-api.sales.payment-forms');
    Route::get('/dashboard-api/sales/geographic', [SalesController::class, 'geographic'])
        ->name('dashboard-api.sales.geographic');
    Route::get('/dashboard-api/sales/app', [SalesController::class, 'app'])
        ->name('dashboard-api.sales.app');
    Route::get('/dashboard-api/sales/orders', [SalesController::class, 'orders'])
        ->name('dashboard-api.sales.orders');
    Route::get('/dashboard-api/appointments/catalog', [AppointmentController::class, 'catalog'])
        ->name('dashboard-api.appointments.catalog');
    Route::get('/dashboard-api/appointments', [AppointmentController::class, 'index'])
        ->name('dashboard-api.appointments.index');
    Route::get('/dashboard-api/claims', [ClaimController::class, 'index'])
        ->name('dashboard-api.claims.index');
    Route::get('/dashboard-api/claims/export', [ClaimController::class, 'export'])
        ->name('dashboard-api.claims.export');
    Route::post('/dashboard-api/claims', [ClaimController::class, 'store'])
        ->name('dashboard-api.claims.store');
    Route::post('/dashboard-api/claims/{claim}', [ClaimController::class, 'update'])
        ->name('dashboard-api.claims.update');
    Route::delete('/dashboard-api/claims/{claim}', [ClaimController::class, 'destroy'])
        ->name('dashboard-api.claims.destroy');
    Route::get('/dashboard-api/subscribers', [SubscriberController::class, 'index'])
        ->name('dashboard-api.subscribers.index');
    Route::post('/dashboard-api/subscribers', [SubscriberController::class, 'store'])
        ->name('dashboard-api.subscribers.store');
    Route::post('/dashboard-api/subscribers/{subscriber}', [SubscriberController::class, 'update'])
        ->name('dashboard-api.subscribers.update');
    Route::delete('/dashboard-api/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])
        ->name('dashboard-api.subscribers.destroy');
    Route::get('/dashboard-api/user-country-access', [UserCountryAccessController::class, 'index'])
        ->name('dashboard-api.user-country-access.index');
    Route::get('/dashboard-api/user-country-access/users', [UserCountryAccessController::class, 'users'])
        ->name('dashboard-api.user-country-access.users');
    Route::post('/dashboard-api/user-country-access', [UserCountryAccessController::class, 'store'])
        ->name('dashboard-api.user-country-access.store');
    Route::delete('/dashboard-api/user-country-access/{assignment}', [UserCountryAccessController::class, 'destroy'])
        ->name('dashboard-api.user-country-access.destroy');
    Route::get('/dashboard-api/orders/reference', [OrderController::class, 'showByReference'])
        ->name('dashboard-api.orders.reference');
    Route::get('/dashboard-api/orders/search', [OrderController::class, 'search'])
        ->name('dashboard-api.orders.search');
    Route::get('/dashboard-api/orders/payment-attempts', [OrderController::class, 'paymentAttempts'])
        ->name('dashboard-api.orders.payment-attempts');
    Route::get('/dashboard-api/orders/refunds', [OrderController::class, 'refunds'])
        ->name('dashboard-api.orders.refunds');
    Route::get('/dashboard-api/orders/refunds/{order}/pdf', [OrderController::class, 'refundPdf'])
        ->name('dashboard-api.orders.refunds.pdf');
    Route::get('/dashboard-api/orders/processed-pdf', [OrderController::class, 'processedPdf'])
        ->name('dashboard-api.orders.processed-pdf');
    Route::get('/dashboard-api/reports/store/catalog', [StoreReportController::class, 'catalog'])
        ->name('dashboard-api.reports.store.catalog');
    Route::get('/dashboard-api/reports/store/virtual-cut', [StoreReportController::class, 'virtualCut'])
        ->name('dashboard-api.reports.store.virtual-cut');
    Route::get('/dashboard-api/reports/store/virtual-cut/pdf', [StoreReportController::class, 'virtualCutPdf'])
        ->name('dashboard-api.reports.store.virtual-cut.pdf');
    Route::get('/dashboard-api/reports/store/pending-items', [StoreReportController::class, 'pendingItems'])
        ->name('dashboard-api.reports.store.pending-items');
    Route::get('/dashboard-api/reports/store/pending-items-by-order', [StoreReportController::class, 'pendingItemsByOrder'])
        ->name('dashboard-api.reports.store.pending-items-by-order');
    Route::get('/dashboard-api/reports/accounting/3/count', [AccountingReportController::class, 'count3'])
        ->name('dashboard-api.reports.accounting.3.count');
    Route::get('/dashboard-api/reports/accounting/3/export', [AccountingReportController::class, 'export3'])
        ->name('dashboard-api.reports.accounting.3.export');
    Route::get('/dashboard-api/reports/accounting/sales-by-store/pdf', [AccountingReportController::class, 'salesByStorePdf'])
        ->name('dashboard-api.reports.accounting.sales-by-store.pdf');
    Route::get('/dashboard-api/orders/product', [OrderController::class, 'product'])
        ->name('dashboard-api.orders.product');
    Route::post('/dashboard-api/orders/data', [OrderController::class, 'updateData'])
        ->name('dashboard-api.orders.data.update');
    Route::post('/dashboard-api/orders/lines/{line}', [OrderController::class, 'updateLine'])
        ->name('dashboard-api.orders.lines.update');
    Route::post('/dashboard-api/orders/process', [OrderController::class, 'process'])
        ->name('dashboard-api.orders.process');
    Route::post('/dashboard-api/orders/packed-pickup', [OrderController::class, 'markPackedForPickup'])
        ->name('dashboard-api.orders.packed-pickup');
    Route::post('/dashboard-api/orders/route', [OrderController::class, 'markInRoute'])
        ->name('dashboard-api.orders.route');
    Route::post('/dashboard-api/orders/deliver', [OrderController::class, 'deliver'])
        ->name('dashboard-api.orders.deliver');

    Route::get('/colecciones', fn () => Inertia::render('Collections/Index'))
        ->name('collections.index');
    Route::get('/promociones', fn () => Inertia::render('Promotions/Index'))
        ->name('promotions.index');
    Route::get('/productos/categorias', fn () => Inertia::render('Products/Categories'))
        ->name('products.categories');
    Route::get('/productos/catalogo', fn () => Inertia::render('Products/Master'))
        ->name('products.master');
    Route::get('/productos/pais', fn () => Inertia::render('Products/Country'))
        ->name('products.country');
    Route::get('/venta', fn () => Inertia::render('Sales/Index'))
        ->name('sales.index');
    Route::get('/citas', fn () => Inertia::render('Appointments/Index'))
        ->name('appointments.index');
    Route::get('/pedidos/pendientes', fn () => Inertia::render('Orders/Pending'))
        ->name('orders.pending');
    Route::get('/pedidos/procesados', fn () => Inertia::render('Orders/Processed'))
        ->name('orders.processed');
    Route::get('/pedidos/devoluciones', fn () => Inertia::render('Orders/Refunds'))
        ->name('orders.refunds');
    Route::get('/pedidos/reclamos', fn () => Inertia::render('Orders/Claims'))
        ->name('orders.claims');
    Route::get('/pedidos/busqueda', fn () => Inertia::render('Orders/Search'))
        ->name('orders.search');
    Route::get('/pedidos/consulta', fn (Request $request) => Inertia::render('Orders/Reference', [
        'initialCountry' => $request->string('country')->toString(),
        'initialReference' => $request->string('id')->toString(),
    ]))->name('orders.reference');
    Route::get('/reportes/corte-virtual', fn () => Inertia::render('Reports/StoreVirtualCut'))
        ->name('reports.store.virtual-cut');
    Route::get('/reportes/articulos-pendientes', fn () => Inertia::render('Reports/PendingItems'))
        ->name('reports.pending-items');
    Route::get('/reportes/articulos-pendientes-pedido', fn () => Inertia::render('Reports/PendingItemsByOrder'))
        ->name('reports.pending-items-by-order');
    Route::get('/reportes/contabilidad/venta-general-3', fn () => Inertia::render('Reports/Accounting3'))
        ->name('reports.accounting.3');
    Route::get('/reportes/contabilidad/venta-general', fn () => Inertia::render('Reports/AccountingSalesByStore'))
        ->name('reports.accounting.sales-by-store');
    Route::get('/reportes/suscriptores', fn () => Inertia::render('Reports/Subscribers'))
        ->name('reports.subscribers');
    Route::get('/configuracion/usuarios-paises', fn () => Inertia::render('Settings/UserCountryAccess'))
        ->name('settings.user-country-access');

    foreach ([
        '/cupones/mantenimiento' => 'Cupones / Mantenimiento',
        '/cupones/reportes' => 'Cupones / Reportes',
        '/pedidos/gestiones' => 'Pedidos / Gestiones',
        '/reportes/catalogo' => 'Reportes / Catalogo',
        '/reportes/im/venta' => 'Reportes / IM Venta',
        '/reportes/contabilidad/venta-general-2' => 'Reportes / Contabilidad 2',
        '/configuracion/log' => 'Configuracion / LOG',
        '/configuracion/slides' => 'Configuracion / Slides',
        '/configuracion/imagenes' => 'Configuracion / Imagenes',
    ] as $uri => $title) {
        Route::get($uri, fn () => Inertia::render('Modules/Placeholder', [
            'title' => $title,
        ]));
    }
});
