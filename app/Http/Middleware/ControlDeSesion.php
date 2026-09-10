<?php

namespace App\Http\Middleware;

use App\Support\SesionUnica;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre por inactividad y sesión única (manual §4.4.4).
 *
 * Los dos controles viven en el mismo middleware porque ambos deciden lo
 * mismo —si esta petición puede continuar— y hacerlo en un solo lugar evita
 * que se contradigan.
 *
 * El cierre por inactividad se aplica **en el servidor**. La cuenta regresiva
 * del navegador es una cortesía para el usuario, no el control: un cliente que
 * no ejecute el JavaScript no puede quedarse dentro para siempre.
 */
class ControlDeSesion
{
    /** Marca de tiempo de la última actividad, dentro de la sesión. */
    public const LLAVE_ACTIVIDAD = 'sesion.ultima_actividad';

    public function __construct(private SesionUnica $sesionUnica)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            return $next($request);
        }

        if (! $this->sesionUnica->esLaActiva($usuario, $request->session())) {
            return $this->cerrar(
                $request,
                'Tu sesión se cerró porque se inició sesión con tu cuenta en otro dispositivo.',
            );
        }

        if ($this->expiroPorInactividad($request)) {
            $this->sesionUnica->olvidar($usuario);

            return $this->cerrar(
                $request,
                'Tu sesión se cerró por inactividad. Vuelve a iniciar sesión.',
            );
        }

        // Sólo las peticiones que representan actividad real del usuario
        // renuevan el reloj. El sondeo del estado de la sesión NO: si lo
        // hiciera, una pestaña abierta mantendría la sesión viva para
        // siempre y el control no serviría de nada.
        if (! $this->esSondeo($request)) {
            $request->session()->put(self::LLAVE_ACTIVIDAD, now()->timestamp);
        }

        return $next($request);
    }

    private function expiroPorInactividad(Request $request): bool
    {
        $ultima = $request->session()->get(self::LLAVE_ACTIVIDAD);

        if (! is_int($ultima)) {
            return false;
        }

        $limite = (int) config('topkapital.sesion.minutos_inactividad') * 60;

        return (now()->timestamp - $ultima) > $limite;
    }

    private function esSondeo(Request $request): bool
    {
        return $request->routeIs('sesion.estado');
    }

    private function cerrar(Request $request, string $motivo): Response
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // El manual exige informar al cliente el MOTIVO del cierre, no sólo
        // devolverlo al login.
        return redirect()->route('login')->with('error', $motivo);
    }
}
