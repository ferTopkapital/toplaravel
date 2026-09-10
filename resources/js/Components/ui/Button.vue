<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        size?: Size;
        /** Muestra spinner y deshabilita. Se enlaza al `processing` de Inertia. */
        loading?: boolean;
        disabled?: boolean;
        /** Si se pasa, el boton se renderiza como <Link> de Inertia. */
        href?: string;
        type?: 'button' | 'submit' | 'reset';
        block?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        loading: false,
        disabled: false,
        type: 'button',
        block: false,
    },
);

const variants: Record<Variant, string> = {
    primary: 'bg-accent text-accent-fg hover:bg-accent-hover shadow-sm',
    secondary:
        'bg-surface-raised text-fg border border-line hover:border-line-strong hover:bg-surface-sunken',
    ghost: 'text-fg-muted hover:bg-surface-sunken hover:text-fg',
    danger: 'bg-danger-500 text-white hover:brightness-95 shadow-sm',
};

const sizes: Record<Size, string> = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-12 px-6 text-base gap-2.5',
};

const isInactive = computed(() => props.disabled || props.loading);

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-lg font-semibold',
    'transition-[background-color,border-color,color,box-shadow,transform] duration-150',
    'active:scale-[0.98]',
    'disabled:pointer-events-none disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <component
        :is="href && !isInactive ? Link : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="href ? undefined : isInactive"
        :aria-busy="loading || undefined"
        :class="classes"
    >
        <!--
            El spinner ocupa su lugar en el flujo en vez de reemplazar al texto:
            asi el boton no cambia de ancho al enviar y la fila no salta.
        -->
        <svg
            v-if="loading"
            class="size-4 shrink-0 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path
                class="opacity-90"
                fill="currentColor"
                d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"
            />
        </svg>

        <slot />
    </component>
</template>
