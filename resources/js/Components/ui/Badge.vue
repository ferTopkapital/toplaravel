<script setup lang="ts">
import { computed } from 'vue';

type Tone = 'neutral' | 'accent' | 'success' | 'warn' | 'danger' | 'info';

const props = withDefaults(defineProps<{ tone?: Tone }>(), { tone: 'neutral' });

// Tonos suaves: fondo tenue + texto saturado. Se lee bien en ambos temas sin
// necesitar una variante oscura distinta.
const tones: Record<Tone, string> = {
    neutral: 'bg-surface-sunken text-fg-muted',
    accent: 'bg-brand-500/10 text-accent',
    success: 'bg-success-500/10 text-success-500',
    warn: 'bg-warn-500/15 text-warn-500',
    danger: 'bg-danger-500/10 text-danger-500',
    info: 'bg-info-500/10 text-info-500',
};

const classes = computed(() => tones[props.tone]);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="classes"
    >
        <slot />
    </span>
</template>
