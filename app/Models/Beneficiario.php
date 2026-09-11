<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficiario designado por el inversionista (`beneficiario`).
 *
 * El manual (§1.1.2) aclara que esta información NO se pide en el onboarding:
 * el cliente puede capturarla cuando quiera desde su perfil.
 *
 * 44 columnas, con datos personales de un tercero (CURP, RFC, domicilio).
 * Acota siempre lo que sale hacia el navegador.
 */
class Beneficiario extends Model
{
    protected $table = 'beneficiario';

    protected $primaryKey = 'beneficiarioId';

    public $timestamps = false;

    protected $hidden = ['CURP', 'RFC'];

    protected function casts(): array
    {
        return [
            'beneficiarioId' => 'integer',
            'usuarioId' => 'integer',
            'porcentaje' => 'decimal:2',
            'fechaNacimiento' => 'date',
            'createdAt' => 'datetime',
            'deletedAt' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuarioId', 'usuarioId');
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
