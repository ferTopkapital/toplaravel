<script setup lang="ts" generic="T extends Record<string, unknown>">
/**
 * Tabla de datos. Reemplaza a los GridView de kartik/yii2-grid.
 *
 * Diferencia clave con el GridView: el orden, el filtro y la paginacion se
 * resuelven EN EL SERVIDOR y viajan por Inertia como recarga parcial
 * (`only`), no recargando la pagina completa. Por eso recibe directamente un
 * paginador de Laravel y no una lista suelta.
 *
 * El filtro se manda con debounce para no disparar una consulta por tecla.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Skeleton from '@/Components/ui/Skeleton.vue';

export type Columna<R> = {
    /** Nombre de la columna tal como lo espera el servidor para ordenar. */
    key: string;
    label: string;
    /** Si es false la columna no ofrece ordenamiento. Por defecto true. */
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    /** Formateo simple. Para celdas ricas usa el slot `celda-<key>`. */
    format?: (fila: R) => string;
};

/** Forma del paginador de Laravel que realmente se usa aqui. */
export type Paginado<R> = {
    data: R[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = withDefaults(
    defineProps<{
        columnas: Columna<T>[];
        datos: Paginado<T>;
        /** Prop de Inertia a recargar. Mantiene el resto de la pagina intacto. */
        only?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        search?: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
    }>(),
    {
        only: 'datos',
        direction: 'asc',
        searchPlaceholder: 'Buscar…',
        emptyMessage: 'No hay registros que mostrar.',
    },
);

const cargando = ref(false);
const consulta = ref(props.search ?? '');

let debounce: ReturnType<typeof setTimeout> | undefined;

function visitar(params: Record<string, unknown>): void {
    router.get(
        window.location.pathname,
        {
            sort: props.sort,
            direction: props.direction,
            search: consulta.value || undefined,
            ...params,
        },
        {
            // Ademas de los datos se recarga el estado del orden y del filtro:
            // sin ellos, `props.sort` se quedaria en el valor viejo y la flecha
            // de la columna nunca se movería.
            only: [props.only, 'sort', 'direction', 'search'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        },
    );
}

function ordenarPor(columna: Columna<T>): void {
    if (columna.sortable === false) return;

    // Mismo click sobre la columna activa: invierte el sentido.
    const direccion = props.sort === columna.key && props.direction === 'asc' ? 'desc' : 'asc';

    visitar({ sort: columna.key, direction: direccion, page: 1 });
}

function irAPagina(pagina: number): void {
    if (pagina < 1 || pagina > props.datos.last_page) return;
    visitar({ page: pagina });
}

watch(consulta, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => visitar({ page: 1 }), 350);
});

const alineacion = {
    left: 'text-left',
    right: 'text-right tabular-nums',
    center: 'text-center',
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-line bg-surface-raised">
        <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
                <svg
                    class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-fg-subtle"
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.6" />
                    <path d="m13.5 13.5 3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>

                <input
                    v-model="consulta"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="h-9 w-full rounded-lg border border-line bg-surface pl-8 pr-3 text-sm text-fg placeholder:text-fg-subtle"
                >
            </div>

            <p class="ml-auto shrink-0 text-xs text-fg-subtle">
                {{ datos.total.toLocaleString('es-MX') }}
                {{ datos.total === 1 ? 'registro' : 'registros' }}
            </p>

            <slot name="acciones" />
        </div>

        <!-- La tabla scrollea dentro de su contenedor; la pagina nunca. -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line bg-surface-sunken">
                        <th
                            v-for="col in columnas"
                            :key="col.key"
                            scope="col"
                            :aria-sort="
                                sort === col.key ? (direction === 'asc' ? 'ascending' : 'descending') : undefined
                            "
                            :class="[
                                'whitespace-nowrap px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-fg-muted',
                                alineacion[col.align ?? 'left'],
                            ]"
                        >
                            <button
                                v-if="col.sortable !== false"
                                type="button"
                                class="inline-flex items-center gap-1 transition-colors hover:text-fg"
                                @click="ordenarPor(col)"
                            >
                                {{ col.label }}
                                <svg
                                    class="size-3 transition-transform duration-150"
                                    :class="[
                                        sort === col.key ? 'text-accent' : 'text-fg-subtle opacity-40',
                                        sort === col.key && direction === 'desc' ? 'rotate-180' : '',
                                    ]"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path d="m10 4v12m0-12 4 4m-4-4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Skeletons con la misma altura de fila: la tabla no salta. -->
                    <template v-if="cargando">
                        <tr v-for="n in 5" :key="`s-${n}`" class="border-b border-line last:border-0">
                            <td v-for="col in columnas" :key="col.key" class="px-4 py-3">
                                <Skeleton class="h-4" />
                            </td>
                        </tr>
                    </template>

                    <template v-else>
                        <tr
                            v-for="(fila, i) in datos.data"
                            :key="i"
                            class="border-b border-line transition-colors last:border-0 hover:bg-surface-sunken"
                        >
                            <td
                                v-for="col in columnas"
                                :key="col.key"
                                :class="['px-4 py-3 text-fg', alineacion[col.align ?? 'left']]"
                            >
                                <slot :name="`celda-${col.key}`" :fila="fila" :valor="fila[col.key]">
                                    {{ col.format ? col.format(fila) : (fila[col.key] ?? '—') }}
                                </slot>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!cargando && !datos.data.length">
                        <td :colspan="columnas.length" class="px-4 py-12 text-center text-sm text-fg-subtle">
                            {{ emptyMessage }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="datos.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3"
        >
            <p class="text-xs text-fg-subtle">
                {{ datos.from ?? 0 }}–{{ datos.to ?? 0 }} de
                {{ datos.total.toLocaleString('es-MX') }}
            </p>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="datos.current_page === 1"
                    class="rounded-md px-3 py-1.5 text-sm text-fg-muted transition-colors hover:bg-surface-sunken hover:text-fg disabled:pointer-events-none disabled:opacity-40"
                    @click="irAPagina(datos.current_page - 1)"
                >
                    Anterior
                </button>

                <span class="px-2 text-sm text-fg-muted">
                    {{ datos.current_page }} / {{ datos.last_page }}
                </span>

                <button
                    type="button"
                    :disabled="datos.current_page === datos.last_page"
                    class="rounded-md px-3 py-1.5 text-sm text-fg-muted transition-colors hover:bg-surface-sunken hover:text-fg disabled:pointer-events-none disabled:opacity-40"
                    @click="irAPagina(datos.current_page + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
