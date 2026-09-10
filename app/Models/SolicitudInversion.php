<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inversión de un cliente en una campaña (`solicitud_inversion`).
 *
 * Es la tabla del dinero del cliente. Los filtros de abajo no son azúcar
 * sintáctica: definen qué cuenta como inversión vigente, y de ahí salen las
 * cifras que el inversionista ve en su panel.
 */
class SolicitudInversion extends Model
{
    protected $table = 'solicitud_inversion';

    protected $primaryKey = 'inversionId';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'inversionId' => 'integer',
            'usuarioId' => 'integer',
            'proyectoId' => 'integer',
            'campanaId' => 'integer',
            'monto' => 'decimal:2',
            'confirmada' => 'boolean',
            'devuelto' => 'boolean',
            'cancelada' => 'boolean',
            'depositoConfirmado' => 'boolean',
            'createdAt' => 'datetime',
            'fechaConfirmacion' => 'datetime',
            'fechaCancelada' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuarioId', 'usuarioId');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyectoId', 'proyectoId');
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campanaId', 'campanaId');
    }

    /**
     * Inversiones que cuentan como capital vigente del cliente.
     *
     * Mismo criterio que usa la app Yii2 para el capital invertido:
     * confirmada y no devuelta. Se añade `cancelada = 0` porque una inversión
     * cancelada tampoco es capital vigente.
     *
     * Cambiar este scope cambia TODAS las cifras del panel. No tocarlo sin
     * comparar contra lo que muestra la app Yii2 para el mismo usuario.
     */
    public function scopeVivas(Builder $query): Builder
    {
        return $query
            ->where('confirmada', 1)
            ->where('devuelto', 0)
            ->where(fn ($q) => $q->whereNull('cancelada')->orWhere('cancelada', 0));
    }

    /** Compromisos aún sin confirmar el depósito. */
    public function scopePendientes(Builder $query): Builder
    {
        return $query
            ->where('confirmada', 0)
            ->where(fn ($q) => $q->whereNull('cancelada')->orWhere('cancelada', 0));
    }

    public function scopeDeUsuario(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuarioId', $usuarioId);
    }
}
