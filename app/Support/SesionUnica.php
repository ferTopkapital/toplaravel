<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Registro de la sesión activa de cada cliente.
 *
 * El manual (§4.4.4) obliga a "impedir el acceso de forma simultánea en la
 * plataforma mediante la utilización de un mismo Identificador de Cliente".
 *
 * ¿Por qué un token propio y no el id de sesión?
 *
 * Porque el id de sesión CAMBIA sin que el usuario haya hecho nada raro:
 * Laravel lo regenera al iniciar sesión y conviene regenerarlo también al
 * cambiar la contraseña, para cortar la fijación de sesión. Si el control
 * comparara ids, esa regeneración legítima expulsaría al usuario de su propia
 * sesión y parecería un acceso desde otro dispositivo.
 *
 * El token vive DENTRO de la sesión, así que sobrevive a la regeneración del
 * id y sigue siendo imposible de adivinar desde fuera.
 *
 * ¿Por qué en caché y no en la base?
 *
 * La tabla `usuario` se comparte con la app Yii2 y no se puede alterar; no hay
 * ninguna columna donde quepa (`codigoSesion` es varchar(8)). Además las
 * sesiones de ambas apps son independientes durante la migración, así que este
 * registro sólo debe hablar de las de esta app.
 *
 * Ojo con el driver: con `file` esto vale para una sola máquina. Si la app
 * llega a correr en varias instancias, el store tiene que ser compartido
 * (Redis o base) o el control deja de valer.
 */
class SesionUnica
{
    /** Llave del token dentro de la sesión del usuario. */
    public const LLAVE_TOKEN = 'sesion.token';

    /** Margen sobre la inactividad: la entrada no debe morir antes que la sesión. */
    private const HOLGURA_MINUTOS = 60;

    /**
     * Marca esta sesión como la única válida del usuario.
     *
     * Cualquier otra sesión abierta con el mismo identificador de cliente
     * quedará fuera en su siguiente petición.
     */
    public function registrar(Usuario $usuario, Session $sesion): string
    {
        $token = Str::random(40);

        $sesion->put(self::LLAVE_TOKEN, $token);

        Cache::put(
            $this->llave($usuario->getKey()),
            $token,
            now()->addMinutes($this->minutosDeVida()),
        );

        return $token;
    }

    /**
     * ¿Esta sesión sigue siendo LA sesión activa del usuario?
     *
     * Si no hay nada registrado se responde que sí: puede ser una sesión
     * anterior a que existiera este control, o una entrada de caché que
     * caducó. Cerrarle la sesión a alguien por eso sería un falso positivo.
     */
    public function esLaActiva(Usuario $usuario, Session $sesion): bool
    {
        $registrado = Cache::get($this->llave($usuario->getKey()));

        if (! is_string($registrado) || $registrado === '') {
            return true;
        }

        $token = $sesion->get(self::LLAVE_TOKEN);

        if (! is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($registrado, $token);
    }

    public function olvidar(Usuario $usuario): void
    {
        Cache::forget($this->llave($usuario->getKey()));
    }

    private function llave(int|string $usuarioId): string
    {
        return "sesion_activa:{$usuarioId}";
    }

    private function minutosDeVida(): int
    {
        return (int) config('topkapital.sesion.minutos_inactividad') + self::HOLGURA_MINUTOS;
    }
}
