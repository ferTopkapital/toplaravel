<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de rendimiento a un inversionista (`retornos`).
 *
 * Es el registro del dinero que sale hacia el cliente: capital, intereses,
 * retención de ISR, comisión y neto, más los folios del CFDI y de la
 * constancia de retención.
 *
 * **Aquí NO se calcula nada.** Todos los importes se leen tal cual están
 * guardados; el motor que los produce (`AmortizationService`,
 * `InteresMoratorioService`) es de la Fase 7 y se migrará comparando
 * resultado contra resultado con la app Yii2. Recalcular por nuestra cuenta
 * lo que el cliente ya vio en un CFDI timbrado sería introducir una
 * discrepancia fiscal.
 *
 * Ojo con la precisión: varias columnas de importe son `decimal(x,6)`, no
 * `(x,2)`. Se conservan como vienen y se redondean **sólo al mostrar**.
 */
class Retorno extends Model
{
    protected $table = 'retornos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /** Un retorno ya liquidado, es decir, dinero efectivamente enviado. */
    public const ESTADO_LIQUIDACION = 'liquidacion';

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'inversionId' => 'integer',
            'calendarioId' => 'integer',
            'fechaCalendario' => 'date',
            'fechaPago' => 'datetime',
            'fechaOperacion' => 'datetime',
            'fechaProcesado' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(SolicitudInversion::class, 'inversionId', 'inversionId');
    }

    /**
     * Retornos de las inversiones de un cliente.
     *
     * Se filtra por el dueño de la inversión, no por una columna propia:
     * `retornos` no tiene `usuarioId`.
     */
    public function scopeDeUsuario(Builder $query, int $usuarioId): Builder
    {
        return $query->whereHas('inversion', fn ($q) => $q->where('usuarioId', $usuarioId));
    }

    /** Ya pagados. */
    public function scopeLiquidados(Builder $query): Builder
    {
        return $query->where('estadoOperacion', self::ESTADO_LIQUIDACION);
    }

    /** Programados a futuro y todavía sin liquidar. */
    public function scopeProgramados(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('estadoOperacion')
                ->orWhere('estadoOperacion', '!=', self::ESTADO_LIQUIDACION))
            ->whereNotNull('fechaCalendario');
    }

    public function estaLiquidado(): bool
    {
        return $this->estadoOperacion === self::ESTADO_LIQUIDACION;
    }

    /*
    |--------------------------------------------------------------------------
    | Rutas de los documentos en S3
    |--------------------------------------------------------------------------
    |
    | El layout lo fija la app Yii2 (common\models\CFDI y common\models\Constancia).
    | No se exponen estas rutas al navegador: se sirven por `DocumentoController`,
    | que primero comprueba que el documento sea del cliente que lo pide.
    |
    */

    public function rutaCfdi(): ?string
    {
        return blank($this->cfdi) ? null : "retornos/{$this->id}/facturas/{$this->cfdi}";
    }

    public function rutaConstancia(): ?string
    {
        return blank($this->constancia) ? null : "retornos/{$this->id}/constancia/{$this->constancia}";
    }

    /** Importe a mostrar, redondeado sólo para la vista. */
    public function importe(string $columna): ?float
    {
        $valor = $this->{$columna};

        return $valor === null ? null : round((float) $valor, 2);
    }
}
