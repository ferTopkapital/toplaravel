<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Badge from '@/Components/ui/Badge.vue';
import { dineroCorto, porcentaje } from '@/Components/MoneyFormat';

const props = defineProps<{
    proyectoId: number;
    nombre: string;
    direccion?: string | null;
    imagen?: string | null;
    objetivo: number;
    fondeado: number;
    avance: number;
    tasa: number;
    plazo: number | null;
    diasRestantes: number | null;
    enFondeo: boolean;
}>();

/** Urgencia visible sólo cuando de verdad queda poco. */
const urgente = computed(
    () => props.enFondeo && props.diasRestantes !== null && props.diasRestantes <= 7,
);
</script>

<template>
    <Link
        :href="`/proyectos/${proyectoId}`"
        class="group flex flex-col overflow-hidden rounded-xl border border-line bg-surface-raised shadow-sm
               transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
    >
        <div class="relative aspect-[16/10] overflow-hidden bg-surface-sunken">
            <img
                v-if="imagen"
                :src="imagen"
                :alt="nombre"
                loading="lazy"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
            >
            <!--
                Marcador propio cuando el proyecto no tiene foto, en vez de una
                imagen generica: asi se distingue de una foto que no cargo.
            -->
            <div v-else class="flex size-full items-center justify-center text-fg-subtle">
                <svg class="size-10" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 20V9l9-6 9 6v11a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="absolute left-3 top-3 flex gap-1.5">
                <Badge :tone="enFondeo ? 'accent' : 'neutral'">
                    {{ enFondeo ? 'En fondeo' : 'Cerrado' }}
                </Badge>
                <Badge v-if="urgente" tone="warn">
                    {{ diasRestantes === 0 ? 'Último día' : `${diasRestantes} días` }}
                </Badge>
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <h3 class="font-display text-lg leading-tight text-fg">{{ nombre }}</h3>
            <p v-if="direccion" class="mt-0.5 truncate text-xs text-fg-subtle">{{ direccion }}</p>

            <div class="mt-3">
                <div class="mb-1.5 flex items-baseline justify-between text-xs">
                    <span class="font-semibold text-fg">{{ dineroCorto(fondeado) }}</span>
                    <span class="text-fg-subtle">de {{ dineroCorto(objetivo) }}</span>
                </div>

                <div
                    class="h-1.5 overflow-hidden rounded-full bg-surface-sunken"
                    role="progressbar"
                    :aria-valuenow="avance"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`Avance de fondeo: ${porcentaje(avance)}`"
                >
                    <div
                        class="h-full rounded-full bg-accent transition-[width] duration-500"
                        :style="{ width: `${Math.min(100, avance)}%` }"
                    />
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-3 text-sm">
                <div>
                    <dt class="text-xs text-fg-subtle">Rendimiento</dt>
                    <dd class="font-semibold tabular-nums text-accent">{{ porcentaje(tasa) }} anual</dd>
                </div>
                <div>
                    <dt class="text-xs text-fg-subtle">Plazo</dt>
                    <dd class="font-semibold tabular-nums text-fg">{{ plazo ?? '—' }} meses</dd>
                </div>
            </dl>
        </div>
    </Link>
</template>
