<script setup lang="ts">
defineProps<{ label?: string; hint?: string; id?: string; disabled?: boolean; error?: string }>();

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <div>
        <label class="flex cursor-pointer items-start gap-2.5" :class="disabled ? 'cursor-not-allowed opacity-50' : ''">
            <!--
                Input nativo con apariencia propia: conserva el comportamiento
                de teclado y de formulario, y solo se repinta el recuadro.
            -->
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                :disabled="disabled"
                class="peer sr-only"
            >

            <span
                class="mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded border transition-all duration-150
                       peer-checked:border-accent peer-checked:bg-accent
                       peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[var(--ring)]"
                :class="error ? 'border-danger-500' : 'border-line-strong'"
            >
                <svg class="size-3 text-accent-fg opacity-0 transition-opacity peer-checked:opacity-100" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 10 3.5 3.5L15 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>

            <span v-if="label || $slots.default" class="text-sm text-fg">
                <slot>{{ label }}</slot>
            </span>
        </label>

        <p v-if="error" class="ml-7 mt-1 text-xs text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="ml-7 mt-1 text-xs text-fg-subtle">{{ hint }}</p>
    </div>
</template>
