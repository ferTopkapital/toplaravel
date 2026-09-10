<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de accesos a la plataforma (`sesiones_web`).
 *
 * NO es un mecanismo de control de sesión pese al nombre: es una bitácora
 * regulatoria. `OficoSeccion1Service` del repo Yii2 la lee para dos campos
 * del reporte que va a la CNBV:
 *
 *   - Fecha del último movimiento del cliente (junto con inversiones y retornos).
 *   - Número de accesos por banca por internet en el periodo.
 *
 * Por eso hay que seguir escribiéndola desde Laravel: si la app nueva deja de
 * hacerlo, el reporte OFICO **subreporta** en cuanto los clientes empiecen a
 * entrar por aquí.
 *
 * Granularidad: UNA fila por usuario y por día. Entrar cinco veces el mismo
 * día cuenta como un acceso, así que la deduplicación no es un detalle de
 * implementación — define el número que se reporta.
 */
class SesionWeb extends Model
{
    protected $table = 'sesiones_web';

    protected $primaryKey = 'sesionId';

    public $timestamps = false;

    protected $fillable = [
        'usuarioId',
        'fechaSesion',
    ];

    protected function casts(): array
    {
        return [
            'usuarioId' => 'integer',
            'fechaSesion' => 'date',
            'createdAt' => 'datetime',
        ];
    }

    /**
     * Deja constancia del acceso de hoy, sin duplicar.
     *
     * Réplica de `common\models\SesionWeb::registrar()` del repo Yii2. La
     * columna `createdAt` la pone la base con su propio `CURRENT_TIMESTAMP`.
     */
    public static function registrar(int $usuarioId): void
    {
        $hoy = now()->toDateString();

        $yaRegistrado = static::query()
            ->where('usuarioId', $usuarioId)
            ->whereDate('fechaSesion', $hoy)
            ->exists();

        if ($yaRegistrado) {
            return;
        }

        static::query()->insert([
            'usuarioId' => $usuarioId,
            'fechaSesion' => $hoy,
        ]);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuarioId', 'usuarioId');
    }
}
