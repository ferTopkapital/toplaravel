<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ControlDeSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado de la sesión, para la cuenta regresiva del navegador.
 *
 * Estas rutas NO son el control de inactividad: el control vive en
 * `ControlDeSesion` y se aplica en el servidor. Esto sólo alimenta el aviso
 * que ve el usuario antes de que se le cierre la sesión, y le da la opción
 * de seguir conectado.
 */
class SesionController extends Controller
{
    /**
     * Segundos que le quedan a la sesión.
     *
     * Esta ruta está excluida a propósito de la renovación de actividad (ver
     * `ControlDeSesion::esSondeo`): si consultar el estado renovara el reloj,
     * una pestaña abierta mantendría la sesión viva indefinidamente.
     */
    public function estado(Request $request): JsonResponse
    {
        return response()->json([
            'restan' => $this->segundosRestantes($request),
            'avisoEn' => (int) config('topkapital.sesion.segundos_aviso'),
        ]);
    }

    /**
     * "No cerrar sesión": el usuario dijo explícitamente que sigue ahí.
     *
     * Esta sí renueva el reloj, porque es una acción deliberada y no un
     * sondeo automático.
     */
    public function renovar(Request $request): JsonResponse
    {
        $request->session()->put(ControlDeSesion::LLAVE_ACTIVIDAD, now()->timestamp);

        return response()->json([
            'restan' => $this->segundosRestantes($request),
        ]);
    }

    private function segundosRestantes(Request $request): int
    {
        $ultima = $request->session()->get(ControlDeSesion::LLAVE_ACTIVIDAD);

        if (! is_int($ultima)) {
            return (int) config('topkapital.sesion.minutos_inactividad') * 60;
        }

        $limite = (int) config('topkapital.sesion.minutos_inactividad') * 60;

        return max(0, $limite - (now()->timestamp - $ultima));
    }
}
