<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solicitante de financiamiento (`promotor`), el desarrollador inmobiliario.
 *
 * 99 columnas: acota siempre el select.
 */
class Promotor extends Model
{
    protected $table = 'promotor';

    protected $primaryKey = 'promotorId';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'promotorId' => 'integer',
            'createdAt' => 'datetime',
        ];
    }
}
