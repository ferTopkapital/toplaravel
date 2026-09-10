<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario de la plataforma: inversionistas, solicitantes y personal interno.
 *
 * Mapea la tabla `usuario` que ya existe y comparte con la app Yii2. NADA en
 * este modelo debe alterar el esquema. Por eso se declaran explícitamente la
 * tabla, la llave primaria y la ausencia de timestamps de Laravel.
 *
 * La tabla tiene 114 columnas. `$fillable` se mantiene acotado a propósito:
 * lo que no esté aquí se asigna a mano, para que un `create()` con datos de
 * un formulario no pueda tocar campos sensibles como `rol` o `estatus`.
 */
class Usuario extends Model implements AuthenticatableContract
{
    use Authorizable;
    use Notifiable;

    protected $table = 'usuario';

    protected $primaryKey = 'usuarioId';

    /** La tabla usa createdAt/modifiedAt, no created_at/updated_at. */
    public $timestamps = false;

    protected $hidden = [
        'passwordHash',
        'auth_key',
        'access_token',
        'codigoLogin',
        'codigoInversion',
        'codigoRecuperarCuenta',
        'codigoSesion',
        'verificationToken',
        'password_reset_token',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => 'integer',
            'rol' => 'integer',
            'intentosLogin' => 'integer',
            'imagenPerfil' => 'integer',
            'terminosCondiciones' => 'boolean',
            'fechaNacimiento' => 'date',
            // Todas las fechas de códigos OTP: si alguna se queda sin cast,
            // CodigoOtp la recibe como texto en vez de Carbon.
            'fechaCodigoLogin' => 'datetime',
            'fechaCodigoRecuperarCuenta' => 'datetime',
            'intentosRecuperarCuenta' => 'integer',
            'ultimoLogin' => 'datetime',
            // El ingreso ANTERIOR. Es el que se le muestra al cliente al
            // iniciar sesión, según el manual §1.4 — no el de ahora mismo.
            'ultimoLoginAnterior' => 'datetime',
            'createdAt' => 'datetime',
            'modifiedAt' => 'datetime',
            'deletedAt' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Constantes del dominio
    |--------------------------------------------------------------------------
    |
    | Copiadas tal cual de common/models/Usuario.php del repo Yii2. Los valores
    | están persistidos en la base compartida: NO se renumeran.
    |
    */

    public const ESTATUS_ELIMINADO = 0;
    public const ESTATUS_SIN_EMAIL = 9;
    public const ESTATUS_SIN_INFO = 10;
    public const ESTATUS_INFO_COMPLETA = 20;
    public const ESTATUS_DOCUMENTOS_VERIFICADOS = 25;
    public const ESTATUS_PRE_AUTORIZADO = 26;
    public const ESTATUS_VERIFICADO_PLD = 30;
    public const ESTATUS_APROBADO_INVERSION = 40;
    public const ESTATUS_APROBADO_PROYECTOS = 41;
    public const ESTATUS_BLOQUEADO_PLD = 100;

    public const ROL_INVERSIONISTA = 10;
    public const ROL_SOLICITANTE = 15;
    public const ROL_COMERCIAL = 18;
    public const ROL_OPERACIONES = 20;
    public const ROL_FINANZAS = 25;
    public const ROL_OFICIAL_PLD = 30;
    public const ROL_SUPER_ADMIN = 40;

    /** Roles que entran por el panel de administración, no por el portal. */
    public const ROLES_INTERNOS = [
        self::ROL_COMERCIAL,
        self::ROL_OPERACIONES,
        self::ROL_FINANZAS,
        self::ROL_OFICIAL_PLD,
        self::ROL_SUPER_ADMIN,
    ];

    /*
    |--------------------------------------------------------------------------
    | Contrato de autenticación
    |--------------------------------------------------------------------------
    |
    | Se implementa a mano en vez de usar el trait Authenticatable porque los
    | nombres de columna no son los que Laravel espera: la llave es `usuarioId`
    | y el hash vive en `passwordHash`.
    |
    */

    public function getAuthIdentifierName(): string
    {
        return 'usuarioId';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash ?? '';
    }

    public function getAuthPasswordName(): string
    {
        return 'passwordHash';
    }

    /*
     * "Recordarme" queda deshabilitado a propósito.
     *
     * El manual (§4.4.4) obliga a cerrar la sesión por inactividad y a impedir
     * sesiones simultáneas; una cookie de sesión persistente contradice ambas
     * cosas. La app Yii2 tampoco lo usa: llama a login($user, 0).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Intencionalmente vacío.
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Estado de la cuenta
    |--------------------------------------------------------------------------
    */

    public function estaEliminado(): bool
    {
        return $this->deletedAt !== null || $this->estatus === self::ESTATUS_ELIMINADO;
    }

    public function estaBloqueadoPorPld(): bool
    {
        return $this->estatus === self::ESTATUS_BLOQUEADO_PLD;
    }

    public function esInterno(): bool
    {
        return in_array($this->rol, self::ROLES_INTERNOS, true);
    }

    /**
     * ¿Se le agotaron los intentos de contraseña?
     *
     * Ojo con el sentido: `intentosLogin` cuenta HACIA ATRÁS desde 10 (es su
     * valor por defecto en la base). Llegar a 0 es el bloqueo del manual
     * §4.3.1, no un contador de fallos acumulados.
     */
    public function agotoIntentos(): bool
    {
        return (int) $this->intentosLogin <= 0;
    }

    public function intentosRestantes(): int
    {
        return max(0, (int) $this->intentosLogin);
    }

    /** Descuenta un intento tras una contraseña incorrecta. */
    public function descontarIntento(): void
    {
        $this->intentosLogin = max(0, (int) $this->intentosLogin - 1);
        $this->save();
    }

    /** Devuelve el contador a su tope tras un ingreso correcto. */
    public function reiniciarIntentos(): void
    {
        $this->intentosLogin = config('topkapital.bloqueo.intentos_maximos');
        $this->save();
    }

    /**
     * ¿Lleva tanto sin entrar que hay que pedirle un código?
     *
     * El manual (§4.3.2) desactiva la cuenta al año de inactividad. La app
     * Yii2 exige OTP a partir de ese mismo umbral.
     */
    public function llevaInactivoDemasiado(): bool
    {
        if ($this->ultimoLogin === null) {
            return false;
        }

        return $this->ultimoLogin->diffInDays(now()) >= config('topkapital.bloqueo.dias_inactividad');
    }

    /**
     * ¿Este ingreso exige el segundo factor?
     *
     * Se evalúa ANTES de validar la contraseña. En la app Yii2 esto fue el bug
     * DDS-897: al validar primero las credenciales, la petición en la que el
     * contador llegaba a 0 —cuando todavía no existía `codigoLogin`— dejaba
     * pasar una contraseña correcta saltándose el OTP.
     */
    public function requiereSegundoFactor(): bool
    {
        return $this->agotoIntentos() || $this->llevaInactivoDemasiado();
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas
    |--------------------------------------------------------------------------
    */

    public static function porEmail(string $email): ?self
    {
        return static::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->whereNull('deletedAt')
            ->first();
    }

    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombre,
            $this->apellidoPaterno,
            $this->apellidoMaterno,
        ])));
    }
}
