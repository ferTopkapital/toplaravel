<?php

namespace App\Services;

use App\Models\DocumentoUsuario;
use App\Models\SolicitudInversion;
use App\Models\Usuario;

/**
 * Bifurcación de KYC (manual §3.1.1, §1.1.2 y §3.2.1).
 *
 * Dos niveles:
 *
 *   Nivel 1 — Simplificado. Personas físicas que invierten hasta $5,000 MXN.
 *             Sólo información básica; sin documentación de identidad.
 *
 *   Nivel 2 — Completo. (i) TODAS las personas morales, sin importar el monto,
 *             y (ii) personas físicas que superen el umbral. Exige la
 *             documentación del artículo 11 de las Disposiciones:
 *             identificación oficial y foto sosteniéndola.
 *
 * Una vez que una persona física cumple el Nivel 2, ese nivel **se mantiene de
 * forma permanente** — no se vuelve a bajar aunque después invierta poco.
 *
 * Dónde se aplica cada cosa, siguiendo a la app Yii2:
 *
 *   - Que las personas morales y los inversionistas Experto o Relacionado
 *     suban documentos se exige al **cerrar el onboarding**
 *     (`UsuarioController::actionValidar`).
 *   - El **umbral por monto** se evalúa al invertir, no al registrarse: un
 *     cliente puede completar su perfil y sólo topar con el requisito cuando
 *     su inversión cruce la línea.
 */
class NivelKyc
{
    public const NIVEL_SIMPLIFICADO = 1;
    public const NIVEL_COMPLETO = 2;

    /** Tipos de inversionista que exigen documentación por sí mismos. */
    public const TIPO_PRINCIPIANTE = 1;
    public const TIPO_EXPERTO = 2;
    public const TIPO_RELACIONADO = 3;

    public function nivelDe(Usuario $usuario): int
    {
        return $this->requiereDocumentacion($usuario)
            ? self::NIVEL_COMPLETO
            : self::NIVEL_SIMPLIFICADO;
    }

    /**
     * ¿Este cliente debe entregar documentación de identidad?
     *
     * Réplica de la condición de `UsuarioController::actionValidar` en la app
     * Yii2: persona moral, o inversionista Experto o Relacionado.
     */
    public function requiereDocumentacion(Usuario $usuario): bool
    {
        if ($this->esPersonaMoral($usuario)) {
            return true;
        }

        return in_array(
            (int) $usuario->tipoInversionista,
            [self::TIPO_EXPERTO, self::TIPO_RELACIONADO],
            true,
        );
    }

    /**
     * ¿Puede invertir este monto, o primero tiene que escalar a Nivel 2?
     *
     * La persona moral queda fuera de esta comprobación **a propósito**: ya
     * tuvo que entregar documentación para terminar su onboarding, así que
     * volver a exigírsela aquí sería redundante. Es el mismo criterio que usa
     * la app Yii2 con su `tipoPersona != 2`.
     */
    public function debeEscalarPara(Usuario $usuario, float $monto): bool
    {
        if ($this->esPersonaMoral($usuario)) {
            return false;
        }

        if ($this->tieneDocumentacionDeInversion($usuario)) {
            return false;
        }

        return ($this->montoAcumulado($usuario) + $monto) > $this->umbral();
    }

    /**
     * Monto ya invertido que cuenta para el umbral.
     *
     * OJO — divergencia conocida con el manual:
     *
     * El manual habla de "monto agregado por **mes calendario**". La app Yii2
     * suma TODAS las inversiones confirmadas del cliente, sin filtro de fecha
     * y sin excluir las devueltas o canceladas.
     *
     * Se conserva ese comportamiento porque es el **más estricto**: acumular
     * de por vida hace que el umbral se cruce antes, es decir, que se pida
     * más documentación, no menos. Cambiarlo a ventana mensual relajaría un
     * control de PLD y debe ser una decisión consciente del Oficial de
     * Cumplimiento, no una "corrección" nuestra.
     *
     * Está anotado en la §8 del plan como discrepancia abierta.
     */
    public function montoAcumulado(Usuario $usuario): float
    {
        return (float) SolicitudInversion::query()
            ->where('usuarioId', $usuario->getKey())
            ->where('confirmada', 1)
            ->sum('monto');
    }

    /**
     * ¿Ya subió identificación y foto sosteniéndola?
     *
     * Réplica de `DocumentoUsuario::tieneDocumentosInversion`: las tres piezas
     * tienen que estar, no basta con una.
     */
    public function tieneDocumentacionDeInversion(Usuario $usuario): bool
    {
        $doc = DocumentoUsuario::deUsuario((int) $usuario->getKey());

        return $doc !== null
            && filled($doc->ineFrente)
            && filled($doc->ineReverso)
            && filled($doc->fotoIne);
    }

    public function umbral(): float
    {
        return (float) config('topkapital.kyc.umbral_nivel_2');
    }

    /** Cuánto más puede invertir antes de tener que escalar. */
    public function margenDisponible(Usuario $usuario): ?float
    {
        if ($this->esPersonaMoral($usuario) || $this->tieneDocumentacionDeInversion($usuario)) {
            return null; // Sin tope por este concepto.
        }

        return max(0.0, $this->umbral() - $this->montoAcumulado($usuario));
    }

    private function esPersonaMoral(Usuario $usuario): bool
    {
        return (int) $usuario->tipoPersona === 2;
    }
}
