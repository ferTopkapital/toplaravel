<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use App\Models\Noticia;
use App\Models\Proyecto;
use App\Models\SolicitudInversion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del inversionista (manual §1.1.2).
 *
 * Las cifras salen del scope `vivas()` de SolicitudInversion, que es el mismo
 * criterio que usa la app Yii2 para el capital invertido: confirmada y no
 * devuelta. **Cualquier cambio a ese criterio cambia lo que el cliente ve como
 * su dinero**, así que se valida contra la app vieja antes de tocarlo.
 */
class PanelController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarioId = (int) $request->user()->getKey();

        $inversiones = SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->vivas();

        $capitalInvertido = (float) (clone $inversiones)->sum('monto');
        $proyectosInvertidos = (int) (clone $inversiones)->distinct()->count('proyectoId');

        $pendientes = SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->pendientes()
            ->count();

        return Inertia::render('Panel/Index', [
            'resumen' => [
                'capitalInvertido' => $capitalInvertido,
                'proyectos' => $proyectosInvertidos,
                'pendientes' => $pendientes,
            ],

            /*
             * Deferred props: el panel pinta de inmediato con el resumen y
             * estas tres secciones llegan en una segunda petición. Es la razón
             * de ser de Inertia 2 aquí — la primera pantalla no espera a las
             * consultas más pesadas.
             */
            'inversiones' => Inertia::defer(fn () => $this->inversionesDelUsuario($usuarioId)),
            'noticias' => Inertia::defer(fn () => $this->noticiasDeSusProyectos($usuarioId)),
            'oportunidades' => Inertia::defer(fn () => $this->campanasEnFondeo()),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function inversionesDelUsuario(int $usuarioId): array
    {
        return SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->vivas()
            ->with([
                'proyecto:proyectoId,nombre,etapaId,imagenes',
                'campana:campanaId,tasaAnualSimple,plazo,fechaFinal',
            ])
            ->orderByDesc('createdAt')
            ->limit(10)
            ->get()
            ->map(fn (SolicitudInversion $i) => [
                'id' => $i->inversionId,
                'monto' => (float) $i->monto,
                'fecha' => $i->fechaConfirmacion?->toIso8601String() ?? $i->createdAt?->toIso8601String(),
                'proyecto' => $i->proyecto?->nombre ?? 'Proyecto',
                'proyectoId' => $i->proyectoId,
                'etapa' => $i->proyecto?->etapa(),
                'tasa' => $i->campana ? (float) $i->campana->tasaAnualSimple : null,
                'plazo' => $i->campana?->plazo,
            ])
            ->all();
    }

    /**
     * Noticias de los proyectos en los que el cliente tiene dinero.
     *
     * Sin inversiones vivas no hay nada que mostrar: se corta antes de
     * consultar, para no lanzar un `IN ()` vacío.
     */
    private function noticiasDeSusProyectos(int $usuarioId): array
    {
        $proyectoIds = SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->vivas()
            ->distinct()
            ->pluck('proyectoId');

        if ($proyectoIds->isEmpty()) {
            return [];
        }

        return Noticia::query()
            ->whereIn('proyectoId', $proyectoIds)
            ->with('proyecto:proyectoId,nombre')
            ->orderByDesc('createdAt')
            ->limit(5)
            ->get()
            ->map(fn (Noticia $n) => [
                'id' => $n->noticiaId,
                'titulo' => $n->titulo,
                'proyecto' => $n->proyecto?->nombre,
                'fecha' => $n->createdAt?->toIso8601String(),
                'pdf' => $n->urlPdf(),
            ])
            ->all();
    }

    /** Campañas abiertas, para invitar a invertir desde el panel. */
    private function campanasEnFondeo(): array
    {
        return Campana::query()
            ->where('etapaId', Proyecto::ETAPA_EN_FONDEO)
            ->whereHas('proyecto', fn ($q) => $q->where('verificacionCompleta', 1)->where('visible', 1))
            ->with('proyecto:proyectoId,nombre,etapaId,imagenes,direccion')
            ->orderByDesc('campanaId')
            ->limit(3)
            ->get()
            ->map(fn (Campana $c) => [
                'proyectoId' => $c->proyectoId,
                'nombre' => $c->proyecto?->nombre ?? 'Proyecto',
                'imagen' => $c->proyecto?->portada(),
                'tasa' => (float) $c->tasaAnualSimple,
                'plazo' => $c->plazo,
                'avance' => $c->avance(),
                'diasRestantes' => $c->diasRestantes(),
            ])
            ->all();
    }
}
