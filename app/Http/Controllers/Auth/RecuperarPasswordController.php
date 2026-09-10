<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CodigoVerificacion;
use App\Models\HistorialContrasena;
use App\Models\Usuario;
use App\Rules\PoliticaPassword;
use App\Services\CodigoOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Restablecimiento de contraseña (manual §4.2.4 y §4.4.2).
 *
 *   Paso 1  solicitar  → captura el correo, se envía un código
 *   Paso 2  verificar  → captura el código
 *   Paso 3  restablecer→ captura la nueva contraseña
 *
 * Es también el camino de desbloqueo cuando la cuenta agotó sus 10 intentos
 * (§4.3.1): al terminar se reinicia el contador.
 *
 * A diferencia del login, aquí **no** se revela si el correo existe. En el
 * login la imagen de seguridad obliga a distinguir; aquí no hay nada que
 * obligue, así que la respuesta es siempre la misma y no se regala un
 * enumerador de cuentas.
 */
class RecuperarPasswordController extends Controller
{
    /** Campo del código en la tabla `usuario`, distinto al del login. */
    private const CAMPO_CODIGO = 'codigoRecuperarCuenta';

    public function __construct(private CodigoOtp $otp)
    {
    }

    public function solicitar(): Response
    {
        return Inertia::render('Auth/RecuperarPassword', ['paso' => 'solicitar']);
    }

    public function enviarCodigo(Request $request): Response
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['email' => 'correo electrónico']);

        $usuario = Usuario::porEmail($datos['email']);

        // Si existe se manda el código; si no, no se hace nada. En ambos casos
        // la pantalla siguiente es idéntica: quien prueba correos al azar no
        // aprende cuáles están registrados.
        if ($usuario !== null && ! $usuario->estaEliminado()) {
            $codigo = $this->otp->generar($usuario, self::CAMPO_CODIGO);

            Mail::to($usuario->email)->send(new CodigoVerificacion(
                nombre: (string) $usuario->nombre,
                codigo: $codigo,
                motivo: 'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
                vigenciaMinutos: (int) ceil(config('topkapital.otp.vigencia_segundos') / 60),
            ));
        }

        $request->session()->put('recuperar.email', $datos['email']);

        return Inertia::render('Auth/RecuperarPassword', [
            'paso' => 'verificar',
            'email' => $datos['email'],
        ]);
    }

    public function verificarCodigo(Request $request): Response|RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string'],
        ], [], ['codigo' => 'código']);

        $usuario = $this->usuarioDeLaSesion($request);

        if ($usuario === null) {
            return $this->volverAlInicio();
        }

        if (! $this->otp->validar($usuario, $datos['codigo'], self::CAMPO_CODIGO)) {
            throw ValidationException::withMessages([
                'codigo' => 'El código es incorrecto o ya expiró.',
            ]);
        }

        // El código se marca como verificado en sesión pero NO se consume
        // todavía: si se borrara aquí y el usuario fallara la política de
        // contraseñas en el paso 3, se quedaría sin código y tendría que
        // empezar de nuevo. Se consume al terminar.
        $request->session()->put('recuperar.verificado', true);

        return Inertia::render('Auth/RecuperarPassword', [
            'paso' => 'restablecer',
            'email' => $usuario->email,
        ]);
    }

    public function restablecer(Request $request): RedirectResponse
    {
        $usuario = $this->usuarioDeLaSesion($request);

        if ($usuario === null || $request->session()->get('recuperar.verificado') !== true) {
            return $this->volverAlInicio();
        }

        $datos = $request->validate([
            'password' => ['required', 'confirmed', new PoliticaPassword($usuario->email)],
        ], [], ['password' => 'contraseña']);

        // Que el código siga vigente se revalida aquí: entre el paso 2 y el 3
        // pudo agotarse su vigencia de 2 minutos.
        if (! $this->otp->validar($usuario, $usuario->{self::CAMPO_CODIGO}, self::CAMPO_CODIGO)) {
            $request->session()->forget(['recuperar.email', 'recuperar.verificado']);

            return redirect()->route('password.solicitar')
                ->withErrors(['email' => 'El código expiró. Solicita uno nuevo.']);
        }

        if (HistorialContrasena::yaFueUsada((int) $usuario->getKey(), $datos['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Esa contraseña ya la habías usado antes. Elige una distinta.',
            ]);
        }

        $hash = Hash::make($datos['password']);

        $usuario->passwordHash = $hash;
        // Restablecer la contraseña es también el desbloqueo del §4.3.1.
        $usuario->intentosLogin = config('topkapital.bloqueo.intentos_maximos');
        $usuario->intentosRecuperarCuenta = 0;
        $usuario->save();

        HistorialContrasena::registrar((int) $usuario->getKey(), $hash);

        $this->otp->consumir($usuario, self::CAMPO_CODIGO);

        $request->session()->forget(['recuperar.email', 'recuperar.verificado']);

        return redirect()->route('login')
            ->with('success', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
    }

    private function usuarioDeLaSesion(Request $request): ?Usuario
    {
        $email = $request->session()->get('recuperar.email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        $usuario = Usuario::porEmail($email);

        return $usuario === null || $usuario->estaEliminado() ? null : $usuario;
    }

    private function volverAlInicio(): RedirectResponse
    {
        return redirect()->route('password.solicitar')
            ->withErrors(['email' => 'Vuelve a empezar el proceso.']);
    }
}
