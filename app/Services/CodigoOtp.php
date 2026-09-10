<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Segundo factor de autenticación (manual §4.4).
 *
 * Código de 8 caracteres con vigencia de 2 minutos, enviado al correo del
 * cliente. Se exige para: compromiso de inversión, alta o cambio de cuenta
 * destino, cambio de contraseña y consulta de estados de cuenta.
 *
 * La app Yii2 lo genera con `Yii::$app->security->generateRandomString(8)`.
 * Aquí se usa `Str::random()`, que también se apoya en `random_bytes()`.
 */
class CodigoOtp
{
    /**
     * Genera un código y lo persiste en el usuario.
     *
     * Devuelve el código en claro para poder enviarlo por correo; en la base
     * queda junto con su fecha de emisión, que es lo que define la vigencia.
     */
    public function generar(Usuario $usuario, string $campo = 'codigoLogin', ?string $campoFecha = null): string
    {
        $codigo = Str::upper(Str::random(config('topkapital.otp.longitud')));

        $usuario->{$campo} = $codigo;
        $usuario->{$campoFecha ?? $this->campoFechaDe($campo)} = now();
        $usuario->save();

        return $codigo;
    }

    /**
     * ¿El código que capturó el usuario es válido y sigue vigente?
     *
     * Se compara con `hash_equals` para no filtrar información por el tiempo
     * que tarda la comparación.
     */
    public function validar(
        Usuario $usuario,
        ?string $capturado,
        string $campo = 'codigoLogin',
        ?string $campoFecha = null,
    ): bool {
        $esperado = $usuario->{$campo};
        $emitido = $usuario->{$campoFecha ?? $this->campoFechaDe($campo)};

        if (blank($esperado) || blank($capturado) || $emitido === null) {
            return false;
        }

        if ($this->expiro($emitido)) {
            return false;
        }

        return hash_equals((string) $esperado, Str::upper(trim($capturado)));
    }

    /**
     * Invalida el código. Se llama SIEMPRE tras un uso correcto: un código de
     * un solo uso que se quedara en la base seguiría siendo válido durante
     * el resto de su vigencia.
     */
    public function consumir(Usuario $usuario, string $campo = 'codigoLogin', ?string $campoFecha = null): void
    {
        $usuario->{$campo} = null;
        $usuario->{$campoFecha ?? $this->campoFechaDe($campo)} = null;
        $usuario->save();
    }

    /** Segundos que le quedan de vida al código, para la cuenta regresiva. */
    public function segundosRestantes(Usuario $usuario, string $campo = 'codigoLogin', ?string $campoFecha = null): int
    {
        $emitido = $usuario->{$campoFecha ?? $this->campoFechaDe($campo)};

        if ($emitido === null) {
            return 0;
        }

        $restan = config('topkapital.otp.vigencia_segundos') - $emitido->diffInSeconds(now());

        return max(0, (int) $restan);
    }

    private function expiro(Carbon $emitido): bool
    {
        return $emitido->diffInSeconds(now()) > config('topkapital.otp.vigencia_segundos');
    }

    /**
     * Convención de la tabla `usuario`: al campo `codigoLogin` le corresponde
     * `fechaCodigoLogin`, a `codigoRecuperarCuenta` le toca
     * `fechaCodigoRecuperarCuenta`, y así.
     */
    private function campoFechaDe(string $campo): string
    {
        return 'fecha' . ucfirst($campo);
    }
}
