<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expediente documental del inversionista (`documento_usuario`).
 *
 * Guarda tres cosas distintas:
 *
 *   1. La documentación de identidad del artículo 11 de las Disposiciones
 *      (`ineFrente`, `ineReverso`, `fotoIne`, `comprobanteDomicilio`), que
 *      sólo aplica a KYC Nivel 2.
 *   2. La **Constancia Electrónica de Conocimiento de Riesgos**, firmada con
 *      un checkbox (manual §3.1.1).
 *   3. El **Contrato General de Comisión Mercantil**, firmado con firma
 *      autógrafa digitalizada — trazada con el mouse o el dedo.
 *
 * Sobre el contrato: se firma **una sola vez** durante el onboarding y regula
 * todas las operaciones de inversión posteriores. No se vuelve a firmar en
 * cada proyecto.
 *
 * Las marcas de tiempo (`constanciaFirmadoEn`, `contratoFirmadoEn`) y la IP
 * (`ipFirma`) son la evidencia de esas firmas: no se tocan una vez puestas.
 */
class DocumentoUsuario extends Model
{
    protected $table = 'documento_usuario';

    protected $primaryKey = 'documentoId';

    public $timestamps = false;

    /** La firma trazada es un dato biométrico: no sale hacia el navegador. */
    protected $hidden = ['firmaBase64', 'firmaPath'];

    protected function casts(): array
    {
        return [
            'documentoId' => 'integer',
            'usuarioId' => 'integer',
            'documentosValidados' => 'boolean',
            'constanciaFirmadoEn' => 'datetime',
            'contratoFirmadoEn' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuarioId', 'usuarioId');
    }

    public static function deUsuario(int $usuarioId): ?self
    {
        return static::query()->where('usuarioId', $usuarioId)->first();
    }

    /** Devuelve el expediente del cliente, creándolo si es su primera vez. */
    public static function paraUsuario(int $usuarioId): self
    {
        $doc = static::deUsuario($usuarioId);

        if ($doc !== null) {
            return $doc;
        }

        $doc = new static();
        $doc->forceFill([
            'usuarioId' => $usuarioId,
            'createdAt' => now(),
        ]);
        $doc->save();

        return $doc;
    }

    public function constanciaFirmada(): bool
    {
        return $this->constanciaFirmadoEn !== null;
    }

    public function contratoFirmado(): bool
    {
        return $this->contratoFirmadoEn !== null;
    }

    /** Las tres piezas de identidad que exige el KYC Nivel 2. */
    public function identidadCompleta(): bool
    {
        return filled($this->ineFrente)
            && filled($this->ineReverso)
            && filled($this->fotoIne);
    }
}
