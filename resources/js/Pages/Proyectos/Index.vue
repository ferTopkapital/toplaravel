<script setup lang="ts">
/** Catálogo de proyectos (manual §1.1.2). */
import { Head, router } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import ProyectoCard from '@/Components/ProyectoCard.vue';

const props = defineProps<{
    etapaActual: number;
    etapas: { id: number; label: string; total: number }[];
    proyectos: any[];
}>();

function cambiarEtapa(id: number): void {
    // Recarga parcial: sólo viajan las props del listado, no la página entera.
    router.get('/proyectos', { etapa: id }, {
        only: ['proyectos', 'etapaActual'],
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Proyectos" />

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl text-fg">Proyectos</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Oportunidades de inversión en desarrollo inmobiliario.
            </p>
        </div>

        <!-- Pestañas por etapa. El conteo evita que alguien entre a una lista vacía. -->
        <div class="flex flex-wrap gap-1.5 border-b border-line pb-3">
            <button
                v-for="e in etapas"
                :key="e.id"
                type="button"
                :aria-current="e.id === etapaActual ? 'page' : undefined"
                :class="[
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-150',
                    e.id === etapaActual
                        ? 'bg-accent text-accent-fg'
                        : 'text-fg-muted hover:bg-surface-sunken hover:text-fg',
                ]"
                @click="cambiarEtapa(e.id)"
            >
                {{ e.label }}
                <span class="ml-1 text-xs opacity-70">{{ e.total }}</span>
            </button>
        </div>

        <div v-if="proyectos.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ProyectoCard v-for="p in proyectos" :key="p.proyectoId" v-bind="p" />
        </div>

        <Card v-else>
            <p class="py-8 text-center text-sm text-fg-subtle">
                No hay proyectos en esta etapa por ahora.
            </p>
        </Card>
    </div>
</template>
