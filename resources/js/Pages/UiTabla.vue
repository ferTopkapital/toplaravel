<script setup lang="ts">
/**
 * Demo de la DataTable contra datos reales de la base compartida.
 *
 * Sirve como prueba de que orden, filtro y paginacion viajan como recarga
 * parcial de Inertia: al usarla, fijate en que la barra de progreso apenas
 * aparece y la pagina no se recarga.
 */
import { Head } from '@inertiajs/vue3';
import DataTable, { type Columna, type Paginado } from '@/Components/data/DataTable.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';

type Actividad = {
    actividadEconomicaID: number;
    nombre: string;
    riesgo: string;
    categoria: string;
};

defineProps<{
    actividades: Paginado<Actividad>;
    sort: string;
    direction: 'asc' | 'desc';
    search: string;
}>();

const columnas: Columna<Actividad>[] = [
    { key: 'actividadEconomicaID', label: 'Clave', align: 'right' },
    { key: 'nombre', label: 'Actividad económica' },
    { key: 'categoria', label: 'Categoría' },
    { key: 'riesgo', label: 'Riesgo', align: 'center' },
];

// El nivel de riesgo del catalogo de PLD viene como texto libre.
const tonoRiesgo: Record<string, 'success' | 'warn' | 'danger' | 'neutral'> = {
    BAJO: 'success',
    MEDIO: 'warn',
    ALTO: 'danger',
};
</script>

<template>
    <Head title="Tabla de datos" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl text-fg">Tabla de datos</h1>
                <p class="mt-1 text-sm text-fg-muted">
                    Reemplazo del GridView de Kartik. Orden, filtro y paginación en el
                    servidor, sin recargar la página.
                </p>
            </div>
            <Button href="/ui" variant="secondary" size="sm">Volver al catálogo</Button>
        </div>

        <DataTable
            :columnas="columnas"
            :datos="actividades"
            only="actividades"
            :sort="sort"
            :direction="direction"
            :search="search"
            search-placeholder="Buscar actividad…"
        >
            <template #celda-riesgo="{ fila }">
                <Badge :tone="tonoRiesgo[fila.riesgo] ?? 'neutral'">
                    {{ fila.riesgo ?? '—' }}
                </Badge>
            </template>

            <template #celda-categoria="{ fila }">
                <span class="text-fg-muted">{{ fila.categoria }}</span>
            </template>
        </DataTable>

        <Card title="Qué estás viendo">
            <ul class="list-inside list-disc space-y-1.5 text-sm text-fg-muted">
                <li>Datos reales del catálogo <code>actividad_economica</code> (1,214 filas).</li>
                <li>El buscador espera 350&nbsp;ms tras la última tecla antes de consultar.</li>
                <li>
                    Cada interacción recarga <strong>sólo</strong> la prop <code>actividades</code>;
                    el resto de la página se queda como está.
                </li>
                <li>Mientras carga se muestran skeletons con la misma altura de fila, para que la tabla no salte.</li>
            </ul>
        </Card>
    </div>
</template>
