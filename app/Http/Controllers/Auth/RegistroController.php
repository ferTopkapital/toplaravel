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
 * Alta de clientes inversionistas (manual §3.1.1).
 *
 *   1. Formulario: identificador, nombre, contraseña y aceptación de
 *      términos y aviso de privacidad.
 *   2. Verificación del correo con un código de un solo uso.
 *   3. Elección de la imagen de seguridad (§1.4).
 *
 * Después de esto viene el onboarding con la bifurcación de KYC, que es la
 * Fase 4 del plan.
 *
 * La cuenta nace con `estatus = 9` (ESTATUS_SIN_EMAIL) y sólo pasa a 10
 * cuando se verifica el correo, igual que en la app Yii2.
 */
class RegistroController extends Controller
{
    public function __construct(private CodigoOtp $otp)
    {
    }

    public function mostrar(): Response
    {
        return Inertia::render('Auth/Registro');
    }

    public function registrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellidoPaterno' => ['nullable', 'string', 'max:100'],
            'apellidoMaterno' => ['nullable', 'string', 'max:100'],
            'tipoPersona' => ['required', 'integer', 'in:1,2'],
            'password' => ['required', 'confirmed', new PoliticaPassword($request->input('email'))],
            'terminos' => ['accepted'],
        ], [
            'terminos.accepted' => 'Debes aceptar los términos y condiciones y el aviso de privacidad.',
        ], [
            'email' => 'correo electrónico',
            'nombre' => 'nombre',
            'password' => 'contraseña',
        ]);

        $existente = Usuario::porEmail($datos['email']);

        if ($existente !== null && (int) $existente->estatus >= Usuario::ESTATUS_SIN_INFO) {
            // La cuenta ya existe y está verificada. NO se dice: responder
            // "ese correo ya está registrado" convierte el formulario en un
            // detector de clientes. Se sigue al paso de verificación como si
            // todo hubiera ido bien; quien sea el dueño real del buzón no
            // recibirá nada y quien pruebe correos no aprende nada.
            $request->session()->put('registro.email', $datos['email']);

            return redirect()->route('registro.verificar');
        }

        // Una cuenta con estatus 9 es un registro que nunca se verificó: se
        // reutiliza en vez de rechazarlo, para que alguien que no recibió el
        // correo pueda volver a intentarlo.
        $usuario = $existente ?? new Usuario();

        $hash = Hash::make($datos['password']);

        $usuario->forceFill([
            'email' => mb_strtolower(trim($datos['email'])),
            'nombre' => $datos['nombre'],
            'apellidoPaterno' => $datos['apellidoPaterno'] ?? null,
            'apellidoMaterno' => $datos['apellidoMaterno'] ?? null,
            'tipoPersona' => $datos['tipoPersona'],
            'passwordHash' => $hash,
            'estatus' => Usuario::ESTATUS_SIN_EMAIL,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'terminosCondiciones' => 1,
            'intentosLogin' => config('topkapital.bloqueo.intentos_maximos'),
            'createdAt' => $usuario->createdAt ?? now(),
        ]);

        $usuario->save();

        HistorialContrasena::registrar((int) $usuario->getKey(), $hash);

        $this->enviarCodigo($usuario);

        $request->session()->put('registro.email', $usuario->email);

        return redirect()->route('registro.verificar');
    }

    public function verificar(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('registro.email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('registro');
        }

        return Inertia::render('Auth/VerificarCorreo', ['email' => $email]);
    }

    public function confirmarCodigo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string'],
        ], [], ['codigo' => 'código']);

        $usuario = $this->usuarioDeLaSesion($request);

        if ($usuario === null) {
            return redirect()->route('registro');
        }

        if (! $this->otp->validar($usuario, $datos['codigo'])) {
            throw ValidationException::withMessages([
                'codigo' => 'El código es incorrecto o ya expiró.',
            ]);
        }

        $this->otp->consumir($usuario);

        // El correo quedó verificado: la cuenta pasa de 9 a 10.
        $usuario->estatus = Usuario::ESTATUS_SIN_INFO;
        $usuario->save();

        return redirect()->route('login')->with(
            'success',
            'Tu correo quedó verificado. Inicia sesión para completar tu perfil.',
        );
    }

    public function reenviarCodigo(Request $request): RedirectResponse
    {
        $usuario = $this->usuarioDeLaSesion($request);

        if ($usuario !== null) {
            $this->enviarCodigo($usuario);
        }

        return back()->with('success', 'Te enviamos un código nuevo.');
    }

    private function enviarCodigo(Usuario $usuario): void
    {
        $codigo = $this->otp->generar($usuario);

        Mail::to($usuario->email)->send(new CodigoVerificacion(
            nombre: (string) $usuario->nombre,
            codigo: $codigo,
            motivo: 'Para activar tu cuenta en Top Kapital necesitamos verificar tu correo.',
            vigenciaMinutos: (int) ceil(config('topkapital.otp.vigencia_segundos') / 60),
        ));
    }

    /** Sólo devuelve cuentas pendientes de verificar. */
    private function usuarioDeLaSesion(Request $request): ?Usuario
    {
        $email = $request->session()->get('registro.email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        $usuario = Usuario::porEmail($email);

        if ($usuario === null || (int) $usuario->estatus !== Usuario::ESTATUS_SIN_EMAIL) {
            return null;
        }

        return $usuario;
    }
}
