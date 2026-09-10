<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use App\Models\Proyecto;
use App\Models\SolicitudInversion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de proyectos para el inversionista (manual §1.1.2).
 *
 * La vista se arma sobre `campana`, no sobre `proyecto`: un proyecto puede
 * fondearse en varias vueltas y las condiciones (monto, plazo, tasa, etapa)
 * viven en la campaña. Mostrarlas desde el proyecto daría datos de una vuelta
 * anterior.
 */
class ProyectoController extends Controller
{
    public function index(Request $request): Response
    {
        $etapa = (int) $request->query('etapa', Proyecto::ETAPA_EN_FONDEO);

        if (! array_key_exists($etapa, Proyecto::ETAPAS)) {
            $etapa = Proyecto::ETAPA_EN_FONDEO;
        }

        $campanas = Campana::query()
            ->where('etapaId', $etapa)
            ->whereHas('proyecto', fn ($q) => $q->where('verificacionCompleta', 1)->where('visible', 1))
            ->with('proyecto:proyectoId,nombre,etapaId,imagenes,direccion,resumen')
            ->orderByDesc('campanaId')
            ->get();

        return Inertia::render('Proyectos/Index', [
            'etapaActual' => $etapa,
            'etapas' => $this->etapasConConteo(),
            'proyectos' => $campanas
                ->map(fn (Campana $c) => $this->tarjeta($c))
                ->values()
                ->all(),
        ]);
    }

    public function detalle(Request $request, int $proyectoId): Response
    {
        $proyecto = Proyecto::query()
            ->publicos()
            ->with('campanaActual')
            ->findOrFail($proyectoId);

        $campana = $proyecto->campanaActual;
        $usuarioId = (int) $request->user()->getKey();

        // Cuánto lleva puesto ESTE cliente en ESTE proyecto.
        $miInversion = (float) SolicitudInversion::query()
            ->deUsuario($usuarioId)
            ->where('proyectoId', $proyectoId)
            ->vivas()
            ->sum('monto');

        return Inertia::render('Proyectos/Detalle', [
            'proyecto' => [
                'id' => $proyecto->proyectoId,
                'nombre' => $proyecto->nombre,
                'etapa' => $proyecto->etapa(),
                'etapaId' => $proyecto->etapaId,
                'resumen' => $proyecto->resumen,
                'direccion' => $proyecto->direccion,
                // urlDeS3 devuelve null si faltan credenciales: se filtran
                // para que la galería no intente pintar huecos.
                'imagenes' => collect($proyecto->imagenes ?? [])
                    ->map(fn ($archivo) => Proyecto::urlDeS3((string) $archivo))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'campana' => $campana === null ? null : [
                'objetivo' => (float) $campana->monto,
                'minimoFondeo' => (float) $campana->minimoFondeo,
                'inversionMin' => (float) $campana->inversionMin,
                'fondeado' => $campana->montoFondeado(),
                'avance' => $campana->avance(),
                'tasa' => (float) $campana->tasaAnualSimple,
                'plazo' => $campana->plazo,
                'diasRestantes' => $campana->diasRestantes(),
                'enFondeo' => $campana->enFondeo(),
                'nivelRiesgo' => $campana->nivelRiesgo,
            ],
            'miInversion' => $miInversion,
        ]);
    }

    /** Conteo por etapa, para que las pestañas no ofrezcan listas vacías. */
    private function etapasConConteo(): array
    {
        $conteos = Campana::query()
            ->whereHas('proyecto', fn ($q) => $q->where('verificacionCompleta', 1)->where('visible', 1))
            ->selectRaw('etapaId, COUNT(*) as total')
            ->groupBy('etapaId')
            ->pluck('total', 'etapaId');

        return collect(Proyecto::ETAPAS)
            ->map(fn (string $label, int $id) => [
                'id' => $id,
                'label' => $label,
                'total' => (int) ($conteos[$id] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function tarjeta(Campana $c): array
    {
        return [
            'proyectoId' => $c->proyectoId,
            'nombre' => $c->proyecto?->nombre ?? 'Proyecto',
            'direccion' => $c->proyecto?->direccion,
            'imagen' => $c->proyecto?->portada(),
            'objetivo' => (float) $c->monto,
            'fondeado' => $c->montoFondeado(),
            'avance' => $c->avance(),
            'tasa' => (float) $c->tasaAnualSimple,
            'plazo' => $c->plazo,
            'diasRestantes' => $c->diasRestantes(),
            'enFondeo' => $c->enFondeo(),
        ];
    }
}
