<?php

namespace App\Http\Controllers;

use App\Models\Retorno;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario de pagos del inversionista (manual §1.1.2).
 *
 * Se arma sobre `retornos`, no sobre `calendario_pagos`: esa última es el
 * calendario del PROYECTO (una fila por fecha de pago de la campaña), no el
 * del cliente. Lo que cada inversionista cobra, con su capital, sus intereses
 * y su retención de ISR, vive en `retornos` ligado a su inversión.
 *
 * Ningún importe se calcula aquí; ver la nota en el modelo `Retorno`.
 */
class CalendarioController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarioId = (int) $request->user()->getKey();

        return Inertia::render('Calendario/Index', [
            'proximos' => Inertia::defer(fn () => $this->proximos($usuarioId)),
            'pagados' => Inertia::defer(fn () => $this->pagados($usuarioId)),
            'totales' => Inertia::defer(fn () => $this->totales($usuarioId)),
        ]);
    }

    private function proximos(int $usuarioId): array
    {
        return Retorno::query()
            ->deUsuario($usuarioId)
            ->programados()
            ->with('inversion.proyecto:proyectoId,nombre')
            ->orderBy('fechaCalendario')
            ->limit(50)
            ->get()
            ->map(fn (Retorno $r) => [
                'id' => $r->id,
                'fecha' => $r->fechaCalendario?->toIso8601String(),
                'proyecto' => $r->inversion?->proyecto?->nombre,
                'capital' => $r->importe('capital'),
                'interes' => $r->importe('intereses'),
                'neto' => $r->importe('retorno_neto'),
            ])
            ->all();
    }

    private function pagados(int $usuarioId): array
    {
        return Retorno::query()
            ->deUsuario($usuarioId)
            ->liquidados()
            ->with('inversion.proyecto:proyectoId,nombre')
            ->orderByDesc('fechaPago')
            ->limit(50)
            ->get()
            ->map(fn (Retorno $r) => [
                'id' => $r->id,
                'fecha' => $r->fechaPago?->toIso8601String(),
                'proyecto' => $r->inversion?->proyecto?->nombre,
                'capital' => $r->importe('capital'),
                'interes' => $r->importe('intereses'),
                'retencionIsr' => $r->importe('retencion_isr'),
                'neto' => $r->importe('retorno_neto'),
                'cfdi' => $r->rutaCfdi() !== null,
            ])
            ->all();
    }

    /**
     * Acumulados de lo ya cobrado.
     *
     * Se suman en la base y se redondean al final: sumar valores ya
     * redondeados a dos decimales arrastraría diferencias de centavos contra
     * lo que el cliente ve en sus CFDI.
     */
    private function totales(int $usuarioId): array
    {
        $liquidados = Retorno::query()->deUsuario($usuarioId)->liquidados();

        return [
            'interesCobrado' => round((float) (clone $liquidados)->sum('intereses'), 2),
            'isrRetenido' => round((float) (clone $liquidados)->sum('retencion_isr'), 2),
            'netoRecibido' => round((float) (clone $liquidados)->sum('retorno_neto'), 2),
            'pagos' => (clone $liquidados)->count(),
        ];
    }
}
