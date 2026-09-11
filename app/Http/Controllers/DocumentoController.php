<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Retorno;
use App\Models\SolicitudInversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Documentos del inversionista (manual §1.1.2): facturas de intereses
 * cobrados, constancias de retención de ISR y comprobantes de inversión.
 *
 * Seguridad
 * ---------
 * Estos son documentos FISCALES y PERSONALES del cliente. Una URL firmada de
 * S3 caduca, pero no comprueba de quién es el documento: quien tenga el
 * enlace lo abre, y la ruta del objeto queda a la vista.
 *
 * Por eso las rutas de S3 nunca salen al navegador. El cliente pide
 * `/documentos/{tipo}/{id}` y **este controlador verifica que el documento
 * sea suyo** antes de firmar nada. Es el mismo criterio de la app Yii2, que
 * los sirve por `site/file` con la ruta cifrada.
 */
class DocumentoController extends Controller
{
    /** Minutos de vida del enlace firmado. Corto: es para abrirlo ya. */
    private const VIGENCIA_MINUTOS = 5;

    public function index(Request $request): Response
    {
        $usuarioId = (int) $request->user()->getKey();

        return Inertia::render('Documentos/Index', [
            'fiscales' => Inertia::defer(fn () => $this->documentosFiscales($usuarioId)),
            'inversiones' => Inertia::defer(fn () => $this->documentosDeInversion($usuarioId)),
        ]);
    }

    /**
     * Entrega un documento tras comprobar que es del cliente autenticado.
     *
     * Se responde 404 —no 403— cuando el documento existe pero es de otro:
     * un 403 confirmaría que ese id existe, y con documentos fiscales
     * ajenos eso ya es información de más.
     */
    public function ver(Request $request, string $tipo, int $id): RedirectResponse
    {
        $usuarioId = (int) $request->user()->getKey();

        $ruta = match ($tipo) {
            'cfdi', 'constancia-isr' => $this->rutaDeRetorno($tipo, $id, $usuarioId),
            'comprobante', 'constancia' => $this->rutaDeInversion($tipo, $id, $usuarioId),
            default => null,
        };

        if ($ruta === null) {
            throw new NotFoundHttpException('Documento no disponible.');
        }

        $url = Proyecto::urlDeS3($ruta, self::VIGENCIA_MINUTOS);

        if ($url === null) {
            throw new NotFoundHttpException('El documento no se pudo recuperar.');
        }

        return redirect()->away($url);
    }

    private function rutaDeRetorno(string $tipo, int $id, int $usuarioId): ?string
    {
        $retorno = Retorno::query()->deUsuario($usuarioId)->find($id);

        if ($retorno === null) {
            return null;
        }

        return $tipo === 'cfdi' ? $retorno->rutaCfdi() : $retorno->rutaConstancia();
    }

    private function rutaDeInversion(string $tipo, int $id, int $usuarioId): ?string
    {
        $inversion = SolicitudInversion::query()->deUsuario($usuarioId)->find($id);

        if ($inversion === null) {
            return null;
        }

        $archivo = $tipo === 'comprobante' ? $inversion->nombreComprobante : $inversion->nombreConstancia;

        if (blank($archivo)) {
            return null;
        }

        return "usuarios/{$usuarioId}/solicitudes_inversion/{$inversion->inversionId}/archivos/{$archivo}";
    }

    /** CFDIs y constancias de retención, que salen de los retornos pagados. */
    private function documentosFiscales(int $usuarioId): array
    {
        return Retorno::query()
            ->deUsuario($usuarioId)
            ->liquidados()
            ->with('inversion.proyecto:proyectoId,nombre')
            ->orderByDesc('fechaPago')
            ->limit(100)
            ->get()
            ->map(fn (Retorno $r) => [
                'id' => $r->id,
                'fecha' => $r->fechaPago?->toIso8601String(),
                'proyecto' => $r->inversion?->proyecto?->nombre,
                'interes' => $r->importe('intereses'),
                'retencionIsr' => $r->importe('retencion_isr'),
                'neto' => $r->importe('retorno_neto'),
                'cfdi' => $r->rutaCfdi() !== null,
                'constancia' => $r->rutaConstancia() !== null,
            ])
            ->all();
    }

    /** Comprobantes y constancias firmadas al momento de invertir. */
    private function documentosDeInversion(int $usuarioId): array
    {
        return SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->vivas()
            ->with('proyecto:proyectoId,nombre')
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn (SolicitudInversion $i) => [
                'id' => $i->inversionId,
                'fecha' => $i->fechaConfirmacion?->toIso8601String() ?? $i->createdAt?->toIso8601String(),
                'proyecto' => $i->proyecto?->nombre,
                'monto' => (float) $i->monto,
                'comprobante' => filled($i->nombreComprobante),
                'constancia' => filled($i->nombreConstancia),
            ])
            ->all();
    }
}
