<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campaña de fondeo (`campana`).
 *
 * Un proyecto puede fondearse en varias vueltas; cada vuelta es una campaña y
 * es ella —no el proyecto— la que lleva monto objetivo, plazo, tasa y etapa.
 * Al mostrarle condiciones al inversionista SIEMPRE se leen de aquí.
 */
class Campana extends Model
{
    protected $table = 'campana';

    protected $primaryKey = 'campanaId';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'campanaId' => 'integer',
            'proyectoId' => 'integer',
            'etapaId' => 'integer',
            'monto' => 'decimal:2',
            'minimoFondeo' => 'decimal:2',
            'inversionMin' => 'decimal:2',
            'monto_entregado' => 'decimal:2',
            'plazo' => 'integer',
            'periodoPago' => 'integer',
            'tasaAnualSimple' => 'decimal:4',
            'numeroCampana' => 'integer',
            'fechaInicio' => 'date',
            'fechaFinal' => 'date',
            'fecha_entrega' => 'date',
            'createdAt' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyectoId', 'proyectoId');
    }

    public function inversiones(): HasMany
    {
        return $this->hasMany(SolicitudInversion::class, 'campanaId', 'campanaId');
    }

    /** Suma efectivamente fondeada: sólo inversiones vivas. */
    public function montoFondeado(): float
    {
        return (float) $this->inversiones()->vivas()->sum('monto');
    }

    /** Avance de fondeo en porcentaje, acotado a 100. */
    public function avance(): float
    {
        $objetivo = (float) $this->monto;

        if ($objetivo <= 0) {
            return 0.0;
        }

        return min(100.0, round($this->montoFondeado() / $objetivo * 100, 2));
    }

    public function diasRestantes(): ?int
    {
        if ($this->fechaFinal === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->fechaFinal, false));
    }

    public function enFondeo(): bool
    {
        return (int) $this->etapaId === Proyecto::ETAPA_EN_FONDEO;
    }
}
