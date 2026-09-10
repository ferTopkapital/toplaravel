<script setup lang="ts">
/**
 * Campo de moneda en pesos mexicanos.
 *
 * Reemplaza a kartik/money. Mientras el campo tiene el foco se edita el
 * numero en crudo (escribir con separadores puestos es incomodo y mueve el
 * cursor); al perderlo se formatea a es-MX. El v-model siempre expone un
 * `number | undefined`, nunca la cadena formateada: lo que se manda al
 * servidor es el valor, no como se ve.
 */
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        id?: string;
        error?: string;
        hint?: string;
        disabled?: boolean;
        required?: boolean;
        min?: number;
        max?: number;
    }>(),
    { disabled: false, required: false },
);

const model = defineModel<number | undefined>();

const enfocado = ref(false);
const borrador = ref('');

const formateador = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
});

const mostrado = computed(() => {
    if (enfocado.value) return borrador.value;
    return model.value === undefined || Number.isNaN(model.value)
        ? ''
        : formateador.format(model.value);
});

function alEnfocar(): void {
    enfocado.value = true;
    borrador.value = model.value === undefined ? '' : String(model.value);
}

function alEscribir(event: Event): void {
    const crudo = (event.target as HTMLInputElement).value;

    // Se tolera lo que la gente realmente teclea: $, comas y espacios.
    borrador.value = crudo.replace(/[^\d.-]/g, '');

    const n = Number.parseFloat(borrador.value);
    model.value = Number.isNaN(n) ? undefined : n;
}

function alSalir(): void {
    enfocado.value = false;
}
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-fg">
            {{ label }}
            <span v-if="required" class="text-danger-500" aria-hidden="true">*</span>
        </label>

        <input
            :id="id"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            :value="mostrado"
            :disabled="disabled"
            :required="required"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : undefined"
            placeholder="$0.00"
            :class="[
                'h-10 w-full rounded-lg border px-3 text-right text-sm tabular-nums transition-colors duration-150',
                'bg-surface-raised text-fg placeholder:text-fg-subtle',
                'disabled:cursor-not-allowed disabled:opacity-50',
                error ? 'border-danger-500' : 'border-line hover:border-line-strong',
            ]"
            @focus="alEnfocar"
            @input="alEscribir"
            @blur="alSalir"
        >

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-xs text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-fg-subtle">{{ hint }}</p>
    </div>
</template>
