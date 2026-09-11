<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperarPasswordController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SesionController;
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

/*
 * Autenticación (Fase 2). El login es en dos pasos porque el manual §1.4
 * obliga a mostrar la imagen de seguridad antes de pedir la contraseña.
 *
 * El throttle acota el efecto de que la pantalla revele si un correo existe
 * —consecuencia inevitable de ese control— y respalda el bloqueo del §4.3.1.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');

    Route::post('/login/identificar', [LoginController::class, 'identificar'])
        ->middleware('throttle:10,1');

    Route::post('/login', [LoginController::class, 'entrar'])
        ->middleware('throttle:10,1');

    /*
     * Recuperación de contraseña (manual §4.2.4 y §4.4.2). Es también el
     * camino de desbloqueo cuando la cuenta agotó sus 10 intentos (§4.3.1).
     */
    Route::get('/recuperar', [RecuperarPasswordController::class, 'solicitar'])
        ->name('password.solicitar');

    Route::post('/recuperar', [RecuperarPasswordController::class, 'enviarCodigo'])
        ->middleware('throttle:5,1');

    Route::post('/recuperar/verificar', [RecuperarPasswordController::class, 'verificarCodigo'])
        ->middleware('throttle:10,1');

    Route::post('/recuperar/restablecer', [RecuperarPasswordController::class, 'restablecer'])
        ->middleware('throttle:10,1');

    /* Alta de clientes inversionistas (manual §3.1.1). */
    Route::get('/registro', [RegistroController::class, 'mostrar'])->name('registro');

    Route::post('/registro', [RegistroController::class, 'registrar'])
        ->middleware('throttle:5,1');

    Route::get('/registro/verificar', [RegistroController::class, 'verificar'])
        ->name('registro.verificar');

    Route::post('/registro/verificar', [RegistroController::class, 'confirmarCodigo'])
        ->middleware('throttle:10,1');

    Route::post('/registro/reenviar', [RegistroController::class, 'reenviarCodigo'])
        ->middleware('throttle:3,1');
});

Route::post('/logout', [LoginController::class, 'salir'])
    ->middleware('auth')
    ->name('logout');

/*
 * Estado de la sesión, para el aviso con cuenta regresiva.
 *
 * `sesion.estado` está excluida de renovar la actividad (ver ControlDeSesion):
 * si el sondeo renovara el reloj, una pestaña abierta mantendría la sesión
 * viva para siempre. `sesion.renovar` sí la renueva, porque ahí el usuario
 * dijo explícitamente que sigue presente.
 */
Route::middleware('auth')->group(function () {
    Route::get('/sesion/estado', [SesionController::class, 'estado'])->name('sesion.estado');
    Route::post('/sesion/renovar', [SesionController::class, 'renovar'])->name('sesion.renovar');
});

/*
 * Portal del inversionista (Fase 3). Todo detrás de `auth`: son datos
 * financieros del cliente.
 */
Route::middleware('auth')->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('panel');

    Route::get('/proyectos', [ProyectoController::class, 'index'])->name('proyectos');
    Route::get('/proyectos/{proyectoId}', [ProyectoController::class, 'detalle'])
        ->whereNumber('proyectoId')
        ->name('proyectos.detalle');

    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario');

    Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos');

    /*
     * Entrega de documentos fiscales y personales.
     *
     * Las rutas de S3 NUNCA salen al navegador: el controlador comprueba que
     * el documento sea del cliente que lo pide y sólo entonces firma una URL
     * de vida corta. Una URL firmada caduca, pero no verifica de quién es el
     * documento.
     */
    Route::get('/documentos/{tipo}/{id}', [DocumentoController::class, 'ver'])
        ->whereIn('tipo', ['cfdi', 'constancia-isr', 'comprobante', 'constancia'])
        ->whereNumber('id')
        ->name('documentos.ver');

    Route::get('/perfil', [PerfilController::class, 'index'])->name('perfil');
    Route::post('/perfil/imagen', [PerfilController::class, 'guardarImagen'])
        ->name('perfil.imagen');
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
