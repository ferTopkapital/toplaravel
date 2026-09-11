<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuenta bancaria del inversionista (`cuenta_bancaria`).
 *
 * NO se da de alta desde la app. La cuenta se identifica automáticamente al
 * recibir la primera transferencia por STP, validando titular, RFC, monto y
 * referencia contra el perfil del cliente (manual §1.1.2 y §3.1.1). Este
 * modelo existe para CONSULTARLA.
 *
 * Los rendimientos, devoluciones y retiros se depositan siempre en la cuenta
 * desde la que el cliente hizo su primera inversión.
 */
class CuentaBancaria extends Model
{
    protected $table = 'cuenta_bancaria';

    protected $primaryKey = 'cuenta_bancariaId';

    public $timestamps = false;

    /** La CLABE completa no debe salir hacia el navegador. */
    protected $hidden = ['CLABE', 'codigoConfirmacion'];

    protected function casts(): array
    {
        return [
            'cuenta_bancariaId' => 'integer',
            'usuarioId' => 'integer',
            'bancoId' => 'integer',
            'confirmada' => 'boolean',
            'predeterminada' => 'boolean',
            'visible' => 'boolean',
            'createdAt' => 'datetime',
            'deletedAt' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuarioId', 'usuarioId');
    }

    /**
     * Últimos cuatro dígitos, para que el cliente reconozca su cuenta.
     *
     * La interfaz no tiene por qué mostrar los 18 dígitos: con reconocerla
     * basta, y un número completo en pantalla es un dato más que se puede
     * fotografiar por encima del hombro.
     */
    public function clabeEnmascarada(): string
    {
        $clabe = (string) $this->CLABE;

        if (strlen($clabe) < 4) {
            return '—';
        }

        return '•••• ' . substr($clabe, -4);
    }
}
