<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Plantilla Blade raiz. Es la unica vista Blade de la app: todo lo demas
     * son componentes Vue que Inertia monta dentro de ella.
     */
    protected $rootView = 'app';

    /**
     * Props compartidas con TODAS las paginas.
     *
     * Ojo con el tamano: esto viaja en cada respuesta de Inertia. Lo que solo
     * necesite una pantalla va en su controller, no aqui.
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            /*
             * Sólo lo que la interfaz necesita, no el modelo completo: la
             * tabla `usuario` tiene 114 columnas con datos personales y esto
             * viaja en CADA respuesta de Inertia.
             *
             * `ultimoAcceso` es el ingreso ANTERIOR, no el actual: es el dato
             * que el manual §1.4 obliga a mostrarle al cliente para que pueda
             * detectar un acceso que no reconozca.
             */
            'auth' => [
                'usuario' => fn () => $request->user() === null ? null : [
                    'id' => $request->user()->usuarioId,
                    'nombre' => $request->user()->nombre,
                    'nombreCompleto' => $request->user()->nombreCompleto(),
                    'email' => $request->user()->email,
                    'rol' => $request->user()->rol,
                    'esInterno' => $request->user()->esInterno(),
                    'ultimoAcceso' => $request->user()->ultimoLoginAnterior?->toIso8601String(),
                ],
            ],

            'sesion' => [
                'minutosInactividad' => (int) config('topkapital.sesion.minutos_inactividad'),
                'segundosAviso' => (int) config('topkapital.sesion.segundos_aviso'),
            ],

            // Mensajes flash de un solo uso (redirecciones tras guardar).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
