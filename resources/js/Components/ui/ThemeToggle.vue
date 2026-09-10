<script setup lang="ts">
import { useTheme, type ThemePreference } from '@/composables/useTheme';

const { preference, setTheme } = useTheme();

const options: { value: ThemePreference; label: string; icon: string }[] = [
    { value: 'light', label: 'Claro', icon: 'M10 3v2m0 10v2m7-7h-2M5 10H3m12.07-5.07-1.42 1.42M6.35 13.65l-1.42 1.42m10.14 0-1.42-1.42M6.35 6.35 4.93 4.93M13 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' },
    { value: 'dark', label: 'Oscuro', icon: 'M16 11.5A6.5 6.5 0 0 1 8.5 4a6.5 6.5 0 1 0 7.5 7.5Z' },
    { value: 'system', label: 'Sistema', icon: 'M3 5.5h14v8H3v-8Zm4 11h6' },
];
</script>

<template>
    <!--
        Tres estados explicitos en vez de un switch de dos: "sistema" tiene que
        ser visible y elegible, no un estado implicito al que no se puede volver.
    -->
    <div
        role="radiogroup"
        aria-label="Tema de la interfaz"
        class="inline-flex items-center gap-0.5 rounded-lg border border-line bg-surface-raised p-0.5"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="preference === option.value"
            :title="option.label"
            :class="[
                'inline-flex size-7 items-center justify-center rounded-md transition-colors duration-150',
                preference === option.value
                    ? 'bg-accent text-accent-fg'
                    : 'text-fg-subtle hover:bg-surface-sunken hover:text-fg',
            ]"
            @click="setTheme(option.value)"
        >
            <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path
                    :d="option.icon"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
            <span class="sr-only">{{ option.label }}</span>
        </button>
    </div>
</template>
