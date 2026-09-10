<script setup lang="ts" generic="T extends string | number">
/**
 * Reemplazo de kartik/select2.
 *
 * Se construye sobre Reka UI (headless) en vez de un <select> nativo porque
 * necesitamos: busqueda dentro de la lista, tema oscuro, y un desplegable que
 * se vea igual en Windows, macOS y Android — cosa que el nativo no da.
 * La accesibilidad (roles ARIA, navegacion con teclado, typeahead) la aporta
 * Reka UI; aqui solo va el estilo y la busqueda.
 */
import { computed, ref } from 'vue';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';

export type SelectOption<V> = {
    value: V;
    label: string;
    disabled?: boolean;
};

const props = withDefaults(
    defineProps<{
        options: SelectOption<T>[];
        placeholder?: string;
        disabled?: boolean;
        /** Muestra el campo de busqueda. Se activa solo si hay muchas opciones. */
        searchable?: boolean;
        error?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Selecciona una opción',
        disabled: false,
        searchable: undefined,
        error: undefined,
    },
);

const model = defineModel<T | undefined>();

const query = ref('');

/** Con pocas opciones el buscador estorba; a partir de 8 ayuda. */
const showSearch = computed(() => props.searchable ?? props.options.length >= 8);

const filtered = computed(() => {
    const q = query.value.trim().toLocaleLowerCase('es');
    if (!q) return props.options;

    return props.options.filter((option) => option.label.toLocaleLowerCase('es').includes(q));
});
</script>

<template>
    <div>
        <SelectRoot v-model="model" :disabled="disabled">
            <SelectTrigger
                :id="id"
                :aria-invalid="!!error"
                :class="[
                    'flex h-10 w-full items-center justify-between gap-2 rounded-lg px-3 text-sm',
                    'bg-surface-raised text-fg border transition-colors duration-150',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    'data-[placeholder]:text-fg-subtle',
                    error ? 'border-danger-500' : 'border-line hover:border-line-strong',
                ]"
            >
                <SelectValue :placeholder="placeholder" class="truncate text-left" />

                <svg class="size-4 shrink-0 opacity-60" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </SelectTrigger>

            <SelectPortal>
                <SelectContent
                    position="popper"
                    :side-offset="6"
                    class="z-50 max-h-72 w-[var(--reka-select-trigger-width)] overflow-hidden rounded-lg border border-line bg-surface-raised shadow-lg
                           data-[state=open]:animate-in data-[state=open]:fade-in-0"
                >
                    <div v-if="showSearch" class="border-b border-line p-2">
                        <input
                            v-model="query"
                            type="text"
                            placeholder="Buscar…"
                            class="h-8 w-full rounded-md border border-line bg-surface px-2 text-sm text-fg placeholder:text-fg-subtle"
                            @keydown.stop
                        >
                    </div>

                    <SelectViewport class="p-1">
                        <SelectItem
                            v-for="option in filtered"
                            :key="String(option.value)"
                            :value="option.value"
                            :disabled="option.disabled"
                            class="relative flex cursor-pointer select-none items-center rounded-md py-2 pl-3 pr-8 text-sm text-fg
                                   data-[highlighted]:bg-surface-sunken data-[highlighted]:outline-none
                                   data-[disabled]:pointer-events-none data-[disabled]:opacity-40"
                        >
                            <SelectItemText>{{ option.label }}</SelectItemText>

                            <SelectItemIndicator class="absolute right-2 inline-flex text-accent">
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m5 10 3.5 3.5L15 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </SelectItemIndicator>
                        </SelectItem>

                        <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-fg-subtle">
                            Sin resultados
                        </p>
                    </SelectViewport>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>

        <p v-if="error" class="mt-1.5 text-xs text-danger-500">{{ error }}</p>
    </div>
</template>
