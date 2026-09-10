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

            'auth' => [
                'user' => $request->user(),
            ],

            // Mensajes flash de un solo uso (redirecciones tras guardar).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
