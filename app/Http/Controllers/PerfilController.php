<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\CuentaBancaria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfil del inversionista (manual §1.1.2).
 *
 * Cinco secciones: información general, beneficiarios, archivos, cuentas
 * bancarias y validación. Aquí van las tres primeras en modo consulta; la
 * captura y el envío a PLD son el wizard de onboarding, que es la Fase 4.
 *
 * **Las cuentas bancarias son de sólo lectura, a propósito.** No es una
 * simplificación: la cuenta del cliente se identifica automáticamente al
 * recibir su primera transferencia por STP, validando titular, RFC, monto y
 * referencia (§1.1.2 y §3.1.1). Poner aquí un formulario de alta rompería ese
 * control.
 */
class PerfilController extends Controller
{
    /** Imágenes de seguridad disponibles; las mismas ocho de la app Yii2. */
    public const IMAGENES_SEGURIDAD = 8;

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $usuarioId = (int) $usuario->getKey();

        return Inertia::render('Perfil/Index', [
            'general' => [
                'nombreCompleto' => $usuario->nombreCompleto(),
                'email' => $usuario->email,
                'rfc' => $usuario->RFC,
                'curp' => $usuario->CURP,
                'telefono' => $usuario->telefono,
                'fechaNacimiento' => $usuario->fechaNacimiento?->toIso8601String(),
                'nacionalidad' => $usuario->nacionalidad,
                'profesion' => $usuario->profesion,
                'regimenFiscal' => $usuario->regimenFiscal,
                'codigoPostal' => $usuario->codigoPostal,
                'estatus' => (int) $usuario->estatus,
                'clabeStp' => $usuario->clabeSTP,
            ],

            'imagenSeguridad' => (int) $usuario->imagenPerfil,
            'imagenesDisponibles' => self::IMAGENES_SEGURIDAD,

            'beneficiarios' => Inertia::defer(fn () => $this->beneficiarios($usuarioId)),
            'cuentas' => Inertia::defer(fn () => $this->cuentas($usuarioId)),
        ]);
    }

    /**
     * Elección de la imagen de seguridad (manual §1.4).
     *
     * Es el mecanismo con el que el cliente verifica, antes de teclear su
     * contraseña, que está en la plataforma legítima y no en una copia.
     */
    public function guardarImagen(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'integer', 'min:1', 'max:' . self::IMAGENES_SEGURIDAD],
        ], [], ['imagen' => 'imagen de seguridad']);

        $usuario = $request->user();
        $usuario->imagenPerfil = $datos['imagen'];
        $usuario->save();

        return back()->with('success', 'Tu imagen de seguridad quedó guardada.');
    }

    private function beneficiarios(int $usuarioId): array
    {
        return Beneficiario::query()
            ->where('usuarioId', $usuarioId)
            ->whereNull('deletedAt')
            ->orderBy('beneficiarioId')
            ->get()
            ->map(fn (Beneficiario $b) => [
                'id' => $b->beneficiarioId,
                'nombre' => $b->nombreCompleto(),
                'parentesco' => $b->parentesco,
                'porcentaje' => $b->porcentaje === null ? null : (float) $b->porcentaje,
            ])
            ->all();
    }

    /**
     * Cuentas identificadas del cliente.
     *
     * Sólo se muestran los últimos dígitos de la CLABE: la interfaz sirve para
     * que el cliente reconozca su cuenta, no para consultar el número completo.
     */
    private function cuentas(int $usuarioId): array
    {
        return CuentaBancaria::query()
            ->where('usuarioId', $usuarioId)
            ->whereNull('deletedAt')
            ->orderByDesc('predeterminada')
            ->get()
            ->map(fn (CuentaBancaria $c) => [
                'id' => $c->cuenta_bancariaId,
                'titular' => $c->nombreTitular,
                'clabe' => $c->clabeEnmascarada(),
                'confirmada' => (bool) $c->confirmada,
                'predeterminada' => (bool) $c->predeterminada,
            ])
            ->all();
    }
}
