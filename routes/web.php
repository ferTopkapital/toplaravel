<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Fase 0 del plan de migracion (docs/PLAN-MIGRACION.md): solo las rutas
| necesarias para comprobar que la fundacion funciona de punta a punta —
| Inertia monta Vue, Tailwind compila y la app lee la base compartida.
|
| Conforme avancen las fases 2 a 6 estas rutas se sustituyen por las reales,
| conservando los mismos paths que la app Yii2 para no romper los enlaces
| que ya salieron en correos.
|
*/

Route::get('/', function () {
    // Consulta directa a proposito: los modelos Eloquent mapeados al esquema
    // existente llegan en la fase 1; aqui solo se valida la conexion.
    $stats = [
        ['label' => 'Usuarios', 'value' => DB::table('usuario')->count()],
        ['label' => 'Proyectos', 'value' => DB::table('proyecto')->count()],
        ['label' => 'Inversiones', 'value' => DB::table('solicitud_inversion')->count()],
        ['label' => 'Promotores', 'value' => DB::table('promotor')->count()],
    ];

    return Inertia::render('Panel', [
        'stats' => $stats,
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
    ]);
});

// Catalogo del design system. Se mantiene durante toda la migracion.
Route::get('/ui', fn () => Inertia::render('Ui'));
