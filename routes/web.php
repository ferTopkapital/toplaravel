<?php

use Illuminate\Http\Request;
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

/*
 * Demo de la DataTable contra una tabla real del negocio.
 *
 * Se usa `actividad_economica` (1,214 filas, catalogo del SAT) a proposito:
 * tiene volumen suficiente para probar orden, filtro y paginacion, y no
 * contiene datos personales. Aqui se valida de punta a punta que la recarga
 * parcial de Inertia funciona contra la base compartida.
 */
Route::get('/ui/tabla', function (Request $request) {
    // Lista blanca: el nombre de columna llega del cliente y se interpola en
    // el ORDER BY, asi que nunca se pasa lo que venga en la query string.
    $ordenables = ['actividadEconomicaID', 'nombre', 'riesgo', 'categoria'];

    $sort = in_array($request->query('sort'), $ordenables, true)
        ? $request->query('sort')
        : 'actividadEconomicaID';

    $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
    $search = trim((string) $request->query('search', ''));

    $actividades = DB::table('actividad_economica')
        ->select('actividadEconomicaID', 'nombre', 'riesgo', 'categoria')
        ->when($search !== '', fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
        ->orderBy($sort, $direction)
        ->paginate(15)
        ->withQueryString();

    return Inertia::render('UiTabla', [
        'actividades' => $actividades,
        'sort' => $sort,
        'direction' => $direction,
        'search' => $search,
    ]);
});
