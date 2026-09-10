<script setup lang="ts">
import { computed } from 'vue';

type Tono = 'info' | 'success' | 'warn' | 'danger';

const props = withDefaults(
    defineProps<{ tone?: Tono; title?: string; dismissible?: boolean }>(),
    { tone: 'info', dismissible: false },
);

const emit = defineEmits<{ close: [] }>();

// Barra lateral de color en vez de fondo saturado: se lee igual de bien en
// tema claro y oscuro sin necesitar dos paletas.
const tonos: Record<Tono, { caja: string; icono: string; ruta: string }> = {
    info: { caja: 'border-l-info-500 bg-info-500/5', icono: 'text-info-500', ruta: 'M10 9v5m0-8h.01' },
    success: { caja: 'border-l-success-500 bg-success-500/5', icono: 'text-success-500', ruta: 'm6 10 3 3 5-6' },
    warn: { caja: 'border-l-warn-500 bg-warn-500/5', icono: 'text-warn-500', ruta: 'M10 7v4m0 3h.01' },
    danger: { caja: 'border-l-danger-500 bg-danger-500/5', icono: 'text-danger-500', ruta: 'M10 7v4m0 3h.01' },
};

const t = computed(() => tonos[props.tone]);
</script>

<template>
    <div
        role="alert"
        class="flex gap-3 rounded-lg border border-line border-l-4 p-4"
        :class="t.caja"
    >
        <svg class="mt-0.5 size-5 shrink-0" :class="t.icono" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5" />
            <path :d="t.ruta" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <div class="min-w-0 flex-1">
            <p v-if="title" class="text-sm font-semibold text-fg">{{ title }}</p>
            <div class="text-sm text-fg-muted" :class="title ? 'mt-0.5' : ''">
                <slot />
            </div>
        </div>

        <button
            v-if="dismissible"
            type="button"
            class="-mr-1 -mt-1 shrink-0 self-start rounded-md p-1 text-fg-subtle transition-colors hover:bg-surface-sunken hover:text-fg"
            aria-label="Cerrar aviso"
            @click="emit('close')"
        >
            <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</template>
