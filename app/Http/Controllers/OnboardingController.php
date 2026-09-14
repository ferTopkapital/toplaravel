<?php

namespace App\Http\Controllers;

use App\Models\DocumentoUsuario;
use App\Models\Usuario;
use App\Services\NivelKyc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding del inversionista (manual §3.1.1).
 *
 * Cinco pasos:
 *
 *   1. Información general  — los datos que enumera el manual.
 *   2. Beneficiarios        — opcional; el manual aclara que NO es parte del
 *                             onboarding y puede completarse en cualquier momento.
 *   3. Archivos             — sólo KYC Nivel 2.
 *   4. Constancia de riesgos — firma por checkbox.
 *   5. Contrato             — firma autógrafa digitalizada.
 *
 * Al terminar, el expediente se envía a revisión del Oficial de Cumplimiento.
 *
 * La carga de archivos del paso 3 llega en la siguiente tanda, junto con la
 * integración de Incode; aquí quedan los pasos que no dependen de subir
 * documentos a S3.
 */
class OnboardingController extends Controller
{
    public function __construct(private NivelKyc $kyc)
    {
    }

    public function wizard(Request $request): Response
    {
        $usuario = $request->user();
        $doc = DocumentoUsuario::paraUsuario((int) $usuario->getKey());

        return Inertia::render('Onboarding/Wizard', [
            'usuario' => [
                'nombre' => $usuario->nombre,
                'apellidoPaterno' => $usuario->apellidoPaterno,
                'apellidoMaterno' => $usuario->apellidoMaterno,
                'tipoPersona' => (int) $usuario->tipoPersona,
                'tipoInversionista' => $usuario->tipoInversionista === null
                    ? null
                    : (int) $usuario->tipoInversionista,
                'RFC' => $usuario->RFC,
                'CURP' => $usuario->CURP,
                'telefono' => $usuario->telefono,
                'fechaNacimiento' => $usuario->fechaNacimiento?->toDateString(),
                'nacionalidad' => $usuario->nacionalidad,
                'profesion' => $usuario->profesion,
                'codigoPostal' => $usuario->codigoPostal,
                'calle' => $usuario->calle,
                'numeroExterior' => $usuario->numeroExterior,
                'numeroInterior' => $usuario->numeroInterior,
                'colonia' => $usuario->colonia,
                'municipio_delegacion' => $usuario->municipio_delegacion,
                'genero' => $usuario->genero === null ? null : (int) $usuario->genero,
                'puestoGobierno' => $usuario->puestoGobierno === null ? null : (int) $usuario->puestoGobierno,
            ],

            'kyc' => [
                'nivel' => $this->kyc->nivelDe($usuario),
                'requiereDocumentacion' => $this->kyc->requiereDocumentacion($usuario),
                'umbral' => $this->kyc->umbral(),
                'acumulado' => $this->kyc->montoAcumulado($usuario),
            ],

            'avance' => [
                'informacion' => $this->informacionCompleta($usuario),
                'archivos' => $doc->identidadCompleta(),
                'constancia' => $doc->constanciaFirmada(),
                'contrato' => $doc->contratoFirmado(),
            ],

            'estatus' => (int) $usuario->estatus,
        ]);
    }

    /** Paso 1: información general (manual §3.1.1). */
    public function guardarInformacion(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $esMoral = (int) $usuario->tipoPersona === 2;

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellidoPaterno' => [$esMoral ? 'nullable' : 'required', 'string', 'max:100'],
            'apellidoMaterno' => ['nullable', 'string', 'max:100'],
            // El RFC de persona moral tiene 12 caracteres; el de física, 13.
            'RFC' => ['required', 'string', $esMoral ? 'size:12' : 'size:13'],
            'CURP' => [$esMoral ? 'nullable' : 'required', 'string', 'size:18'],
            'telefono' => ['required', 'string', 'max:20'],
            'fechaNacimiento' => [$esMoral ? 'nullable' : 'required', 'date', 'before:today'],
            'nacionalidad' => ['required', 'string', 'max:255'],
            'profesion' => ['required', 'string', 'max:255'],
            'codigoPostal' => ['required', 'string', 'max:11'],
            'calle' => ['required', 'string', 'max:255'],
            'numeroExterior' => ['required', 'string', 'max:20'],
            'numeroInterior' => ['nullable', 'string', 'max:20'],
            'colonia' => ['required', 'string', 'max:255'],
            'municipio_delegacion' => ['required', 'string', 'max:255'],
            'genero' => [$esMoral ? 'nullable' : 'required', 'integer', 'in:1,2'],
            'tipoInversionista' => ['required', 'integer', 'in:1,2,3'],
            // "¿Desempeñas o desempeñaste un puesto público?" — dato de PLD.
            'puestoGobierno' => ['required', 'integer', 'in:0,1'],
        ], [], [
            'RFC' => 'RFC',
            'CURP' => 'CURP',
            'municipio_delegacion' => 'municipio o delegación',
            'puestoGobierno' => 'puesto en gobierno',
        ]);

        $usuario->forceFill($datos);

        // El estatus sólo avanza; nunca retrocede porque se reedite el perfil.
        if ((int) $usuario->estatus < Usuario::ESTATUS_INFO_COMPLETA) {
            $usuario->estatus = Usuario::ESTATUS_INFO_COMPLETA;
        }

        $usuario->modifiedAt = now();
        $usuario->save();

        return back()->with('success', 'Tu información quedó guardada.');
    }

    /**
     * Paso 4: Constancia Electrónica de Conocimiento de Riesgos.
     *
     * El manual (§3.1.1) exige que el inversionista la LEA y la firme con un
     * checkbox. Se exigen todas las casillas por separado, no una sola de
     * "acepto todo": cada una corresponde a un riesgo que la institución debe
     * acreditar que reveló.
     */
    public function firmarConstancia(Request $request): RedirectResponse
    {
        $request->validate([
            'riesgoPerdida' => ['accepted'],
            'riesgoLiquidez' => ['accepted'],
            'riesgoInformacion' => ['accepted'],
            'riesgoRendimiento' => ['accepted'],
            'sinAprobacion' => ['accepted'],
            'sinAsesoria' => ['accepted'],
        ], [
            'accepted' => 'Debes reconocer todos los puntos para continuar.',
        ]);

        $doc = DocumentoUsuario::paraUsuario((int) $request->user()->getKey());

        // No se re-firma: la fecha original es la evidencia.
        if (! $doc->constanciaFirmada()) {
            $doc->constanciaFirmadoEn = now();
            $doc->ipFirma = $request->ip();
            $doc->updatedAt = now();
            $doc->save();
        }

        return back()->with('success', 'Constancia de conocimiento de riesgos firmada.');
    }

    /**
     * Paso 5: Contrato General de Comisión Mercantil con firma autógrafa
     * digitalizada (manual §3.1.1).
     *
     * Se firma UNA sola vez y regula todas las inversiones posteriores; no se
     * vuelve a pedir en cada proyecto.
     */
    public function firmarContrato(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            // El canvas manda un PNG en data URI. Se acota el tamaño para que
            // nadie use este campo como almacenamiento.
            'firma' => ['required', 'string', 'max:500000', 'starts_with:data:image/png;base64,'],
        ], [
            'firma.starts_with' => 'La firma no tiene un formato válido.',
            'firma.max' => 'La firma es demasiado grande.',
        ], ['firma' => 'firma']);

        $usuario = $request->user();
        $doc = DocumentoUsuario::paraUsuario((int) $usuario->getKey());

        if ($doc->contratoFirmado()) {
            return back()->with('success', 'Tu contrato ya estaba firmado.');
        }

        if (! $doc->constanciaFirmada()) {
            throw ValidationException::withMessages([
                'firma' => 'Primero debes firmar la constancia de conocimiento de riesgos.',
            ]);
        }

        $doc->firmaBase64 = $datos['firma'];
        $doc->contratoFirmadoEn = now();
        $doc->ipFirma = $request->ip();
        $doc->updatedAt = now();
        $doc->save();

        return back()->with('success', 'Contrato firmado.');
    }

    /**
     * Envío del expediente a revisión del Oficial de Cumplimiento.
     *
     * Réplica de `UsuarioController::actionValidar` de la app Yii2: no se deja
     * enviar mientras falte la constancia, el contrato o —cuando aplica— la
     * documentación de identidad.
     */
    public function enviarAValidacion(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $doc = DocumentoUsuario::paraUsuario((int) $usuario->getKey());

        $faltantes = [];

        if (! $this->informacionCompleta($usuario)) {
            $faltantes[] = 'tu información general';
        }

        if (! $doc->constanciaFirmada()) {
            $faltantes[] = 'la constancia de conocimiento de riesgos';
        }

        if (! $doc->contratoFirmado()) {
            $faltantes[] = 'el contrato de comisión mercantil';
        }

        if ($this->kyc->requiereDocumentacion($usuario) && ! $doc->identidadCompleta()) {
            $faltantes[] = 'tu documentación de identidad';
        }

        if ($faltantes !== []) {
            return back()->withErrors([
                'wizard' => 'Antes de enviar falta completar: ' . implode(', ', $faltantes) . '.',
            ]);
        }

        if ((int) $usuario->estatus < Usuario::ESTATUS_INFO_COMPLETA) {
            $usuario->estatus = Usuario::ESTATUS_INFO_COMPLETA;
            $usuario->modifiedAt = now();
            $usuario->save();
        }

        // TODO(Fase 4): avisar por correo al Oficial de Cumplimiento y a
        // Operaciones, como hace DocumentoUsuario::notificarAdminsRevision.

        return back()->with(
            'success',
            'Tu expediente se envió a revisión. Te avisaremos por correo cuando quede validado.',
        );
    }

    /** Los campos que el manual §3.1.1 enumera como información básica. */
    private function informacionCompleta(Usuario $usuario): bool
    {
        $obligatorios = ['nombre', 'RFC', 'telefono', 'nacionalidad', 'profesion',
            'codigoPostal', 'calle', 'colonia', 'municipio_delegacion'];

        foreach ($obligatorios as $campo) {
            if (blank($usuario->{$campo})) {
                return false;
            }
        }

        return $usuario->tipoInversionista !== null;
    }
}
