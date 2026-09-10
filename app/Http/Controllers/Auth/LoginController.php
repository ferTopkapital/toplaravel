<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ControlDeSesion;
use App\Models\SesionWeb;
use App\Models\Usuario;
use App\Services\CodigoOtp;
use App\Support\SesionUnica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio de sesión en dos pasos.
 *
 * El paso intermedio no es un capricho de diseño: el manual (§1.4) obliga a
 * mostrarle al cliente su imagen de seguridad ANTES de que capture la
 * contraseña, para que pueda verificar que está en la plataforma legítima.
 * Por eso primero se pide el identificador y sólo después las credenciales.
 *
 *   Paso 1  GET  /login              → captura del identificador (correo)
 *   Paso 2  POST /login/identificar  → imagen de seguridad + contraseña
 *           POST /login              → validación y entrada
 *
 * Nota sobre enumeración de usuarios: mostrar la imagen revela si un correo
 * existe. Es una consecuencia inevitable del control que exige el manual, no
 * un descuido. Se compensa limitando la tasa de intentos por IP.
 */
class LoginController extends Controller
{
    public function __construct(
        private CodigoOtp $otp,
        private SesionUnica $sesionUnica,
    ) {
    }

    public function mostrar(): Response
    {
        return Inertia::render('Auth/Login', [
            'paso' => 'identificador',
        ]);
    }

    /**
     * Paso 2: se localiza al usuario y se le muestra su imagen de seguridad.
     *
     * Aquí se decide también si el ingreso exige segundo factor, y de ser así
     * se emite el código. La decisión va ANTES de validar la contraseña a
     * propósito: en la app Yii2 hacerlo al revés causó el bug DDS-897, donde
     * la petición en la que el contador de intentos llegaba a cero dejaba
     * pasar una contraseña correcta sin pedir el OTP.
     */
    public function identificar(Request $request): Response|RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['email' => 'correo electrónico']);

        $usuario = Usuario::porEmail($datos['email']);

        if ($usuario === null || $usuario->estaEliminado()) {
            throw ValidationException::withMessages([
                'email' => 'No encontramos una cuenta con ese correo.',
            ]);
        }

        if ($usuario->estaBloqueadoPorPld()) {
            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está bloqueada. Comunícate con soporte@topkapital.com.',
            ]);
        }

        $requiereOtp = $usuario->requiereSegundoFactor();

        if ($requiereOtp) {
            $codigo = $this->otp->generar($usuario);

            // TODO(Fase 2): enviar por correo. Mientras el mailer no está
            // conectado se deja en el log para poder probar el flujo completo.
            logger()->info("OTP de acceso para {$usuario->email}: {$codigo}");
        }

        // El correo se guarda en sesión para que el paso 2 no dependa de que
        // el cliente lo reenvíe: así no se puede pedir la imagen de un usuario
        // y luego intentar la contraseña de otro.
        $request->session()->put('login.email', $usuario->email);

        return Inertia::render('Auth/Login', [
            'paso' => 'credenciales',
            'email' => $usuario->email,
            'nombre' => $usuario->nombre,
            'imagenSeguridad' => $this->rutaImagenSeguridad($usuario),
            'requiereOtp' => $requiereOtp,
            'segundosOtp' => $requiereOtp ? $this->otp->segundosRestantes($usuario) : 0,
            'intentosRestantes' => $usuario->intentosRestantes(),
        ]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password' => ['required', 'string'],
            'codigo' => ['nullable', 'string'],
        ], [], ['password' => 'contraseña', 'codigo' => 'código']);

        $email = $request->session()->get('login.email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Vuelve a capturar tu correo.']);
        }

        $usuario = Usuario::porEmail($email);

        if ($usuario === null || $usuario->estaEliminado()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Vuelve a capturar tu correo.']);
        }

        // Igual que arriba: la exigencia del segundo factor se determina antes
        // de mirar la contraseña.
        $requiereOtp = $usuario->requiereSegundoFactor();

        if ($requiereOtp && ! $this->otp->validar($usuario, $datos['codigo'] ?? null)) {
            throw ValidationException::withMessages([
                'codigo' => 'El código es incorrecto o ya expiró.',
            ]);
        }

        if (! Hash::check($datos['password'], $usuario->getAuthPassword())) {
            $usuario->descontarIntento();

            throw ValidationException::withMessages([
                'password' => $usuario->agotoIntentos()
                    ? 'Credenciales incorrectas. Por tu seguridad, tu cuenta ahora requiere un código de verificación.'
                    : "Credenciales incorrectas. Te quedan {$usuario->intentosRestantes()} intentos.",
            ]);
        }

        if ($requiereOtp) {
            $this->otp->consumir($usuario);
        }

        $this->registrarIngreso($usuario);

        Auth::login($usuario);

        // Renueva el id de sesión: cierra la puerta a la fijación de sesión.
        $request->session()->regenerate();
        $request->session()->forget('login.email');

        // Deja fuera cualquier otra sesión abierta con este mismo
        // identificador de cliente (manual §4.4.4).
        $this->sesionUnica->registrar($usuario, $request->session());
        $request->session()->put(ControlDeSesion::LLAVE_ACTIVIDAD, now()->timestamp);

        return redirect()->intended('/');
    }

    public function salir(Request $request): RedirectResponse
    {
        if ($usuario = Auth::user()) {
            $this->sesionUnica->olvidar($usuario);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Rota las marcas de tiempo de ingreso y reinicia el contador de intentos.
     *
     * `ultimoLoginAnterior` conserva el ingreso PREVIO: es el dato que el
     * manual (§1.4) obliga a mostrarle al cliente al entrar, no el de ahora.
     */
    private function registrarIngreso(Usuario $usuario): void
    {
        $usuario->ultimoLoginAnterior = $usuario->ultimoLogin;
        $usuario->ultimoLogin = now();
        $usuario->intentosLogin = config('topkapital.bloqueo.intentos_maximos');
        $usuario->save();

        // Bitácora regulatoria: `OficoSeccion1Service` la lee para el número
        // de accesos por banca por internet y la fecha del último movimiento.
        // Si Laravel deja de escribirla, el reporte a la CNBV subreporta en
        // cuanto los clientes empiecen a entrar por esta app.
        SesionWeb::registrar((int) $usuario->getKey());
    }

    /**
     * Imagen de seguridad que el cliente eligió al registrarse.
     *
     * Son las mismas ocho de la app Yii2 (`/images/nft/img-0N.jpg`), copiadas
     * a `public/images/seguridad/`. Si el usuario todavía no elige una, se
     * devuelve null y la pantalla lo indica en vez de mostrar una equivocada:
     * enseñar una imagen que no es la suya rompería justamente la garantía
     * que este control existe para dar.
     */
    private function rutaImagenSeguridad(Usuario $usuario): ?string
    {
        $indice = (int) $usuario->imagenPerfil;

        if ($indice < 1 || $indice > 8) {
            return null;
        }

        return sprintf('/images/seguridad/img-%02d.jpg', $indice);
    }
}
