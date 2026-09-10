<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Historial de contraseñas (`historial_contrasenas`).
 *
 * Sirve para impedir que un cliente reutilice una contraseña anterior al
 * cambiarla o restablecerla. Se guarda el hash, nunca el texto plano, así que
 * la comparación es una por una contra `Hash::check()`; no hay forma de
 * buscarlo con un WHERE.
 */
class HistorialContrasena extends Model
{
    protected $table = 'historial_contrasenas';

    protected $primaryKey = 'contrasenaId';

    public $timestamps = false;

    protected $fillable = [
        'usuarioId',
        'passwordHash',
    ];

    protected function casts(): array
    {
        return [
            'usuarioId' => 'integer',
            'createdAt' => 'datetime',
        ];
    }

    /**
     * ¿El usuario ya había usado esta contraseña?
     *
     * Cuántas se comparan lo decide `topkapital.password.historial_comparado`;
     * `null` significa todo el historial, que es el comportamiento vigente en
     * la app Yii2 (ver el comentario en ese archivo de configuración).
     *
     * Cada comprobación es un bcrypt de coste 13, así que esto es
     * deliberadamente lento. Está bien: sólo corre al cambiar contraseña.
     */
    public static function yaFueUsada(int $usuarioId, string $passwordPlano): bool
    {
        $limite = config('topkapital.password.historial_comparado');

        $anteriores = static::query()
            ->where('usuarioId', $usuarioId)
            ->orderByDesc('createdAt')
            ->when($limite !== null, fn ($q) => $q->limit((int) $limite))
            ->pluck('passwordHash');

        foreach ($anteriores as $hash) {
            if (is_string($hash) && $hash !== '' && Hash::check($passwordPlano, $hash)) {
                return true;
            }
        }

        return false;
    }

    /** Deja constancia del hash recién asignado. */
    public static function registrar(int $usuarioId, string $passwordHash): void
    {
        static::query()->insert([
            'usuarioId' => $usuarioId,
            'passwordHash' => $passwordHash,
        ]);
    }
}
