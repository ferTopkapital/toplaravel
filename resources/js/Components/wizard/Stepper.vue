<script setup lang="ts">
/**
 * Indicador de pasos para los wizards.
 *
 * La app tiene cinco wizards (onboarding de inversionista y de solicitante,
 * alta de proyecto, autorizacion de proyecto, flujo de inversion) que hoy
 * repiten su propia barra de pasos en cada vista. Este es el unico.
 *
 * Solo permite volver a pasos ya completados: saltar hacia adelante se
 * bloquea porque cada paso valida y guarda antes de habilitar el siguiente.
 */
import { computed } from 'vue';

export type Paso = {
    id: string | number;
    label: string;
    /** Texto corto opcional bajo la etiqueta. */
    hint?: string;
};

const props = defineProps<{
    pasos: Paso[];
    /** Indice del paso actual, base 0. */
    actual: number;
    /** Ultimo indice que el usuario ya completo y puede revisitar. */
    completadoHasta?: number;
}>();

const emit = defineEmits<{ ir: [indice: number] }>();

const maxVisitable = computed(() => props.completadoHasta ?? props.actual);

function estado(i: number): 'completo' | 'actual' | 'pendiente' {
    if (i < props.actual) return 'completo';
    if (i === props.actual) return 'actual';
    return 'pendiente';
}

function irA(i: number): void {
    if (i <= maxVisitable.value && i !== props.actual) {
        emit('ir', i);
    }
}
</script>

<template>
    <ol class="flex w-full items-start gap-1">
        <li v-for="(paso, i) in pasos" :key="paso.id" class="flex flex-1 flex-col items-center">
            <div class="flex w-full items-center">
                <!-- Linea izquierda; invisible en el primero para no colgar. -->
                <span
                    class="h-0.5 flex-1 rounded-full transition-colors duration-300"
                    :class="[
                        i === 0 ? 'invisible' : '',
                        i <= actual ? 'bg-accent' : 'bg-line',
                    ]"
                />

                <button
                    type="button"
                    :disabled="i > maxVisitable || i === actual"
                    :aria-current="i === actual ? 'step' : undefined"
                    :class="[
                        'mx-1.5 flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                        'transition-all duration-300',
                        estado(i) === 'completo'
                            ? 'border-accent bg-accent text-accent-fg'
                            : estado(i) === 'actual'
                              ? 'border-accent bg-surface-raised text-accent ring-4 ring-accent/15'
                              : 'border-line bg-surface-raised text-fg-subtle',
                        i <= maxVisitable && i !== actual ? 'cursor-pointer hover:scale-110' : '',
                    ]"
                    @click="irA(i)"
                >
                    <svg
                        v-if="estado(i) === 'completo'"
                        class="size-4"
                        viewBox="0 0 20 20"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path d="m5 10 3.5 3.5L15 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span v-else>{{ i + 1 }}</span>

                    <span class="sr-only">{{ paso.label }}</span>
                </button>

                <span
                    class="h-0.5 flex-1 rounded-full transition-colors duration-300"
                    :class="[
                        i === pasos.length - 1 ? 'invisible' : '',
                        i < actual ? 'bg-accent' : 'bg-line',
                    ]"
                />
            </div>

            <div class="mt-2 px-1 text-center">
                <p
                    class="text-xs font-medium transition-colors"
                    :class="i === actual ? 'text-fg' : 'text-fg-subtle'"
                >
                    {{ paso.label }}
                </p>
                <p v-if="paso.hint" class="mt-0.5 hidden text-[11px] text-fg-subtle sm:block">
                    {{ paso.hint }}
                </p>
            </div>
        </li>
    </ol>
</template>
