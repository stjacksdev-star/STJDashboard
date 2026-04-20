<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\CollectionController;
use App\Http\Middleware\EnsureCasAuthenticated;
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

    Route::get('/colecciones', fn () => Inertia::render('Collections/Index'))
        ->name('collections.index');

    foreach ([
        '/citas' => 'Citas',
        '/venta' => 'Venta',
        '/promociones' => 'Promociones',
        '/cupones/mantenimiento' => 'Cupones / Mantenimiento',
        '/cupones/reportes' => 'Cupones / Reportes',
        '/pedidos/gestiones' => 'Pedidos / Gestiones',
        '/pedidos/pendientes' => 'Pedidos / Pendientes',
        '/pedidos/devoluciones' => 'Pedidos / Devoluciones',
        '/pedidos/busqueda' => 'Pedidos / Busqueda',
        '/pedidos/consulta' => 'Pedidos / Referencia',
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
