<script setup lang="ts">
/**
 * Dialogo modal sobre Reka UI.
 *
 * Reka aporta lo que es facil hacer mal a mano: trampa de foco, cierre con
 * Escape, bloqueo del scroll del fondo y los roles ARIA. Aqui solo va el
 * estilo y las transiciones.
 */
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        /** sm para confirmaciones, lg para formularios largos. */
        size?: 'sm' | 'md' | 'lg';
        /**
         * false quita la X y bloquea Escape y el clic fuera.
         *
         * Para modales donde cerrar sin elegir no significa nada: el aviso de
         * cierre de sesión, por ejemplo, donde descartarlo no evita que la
         * sesión caduque. Úsalo con criterio: atrapar al usuario en un modal
         * es hostil salvo que la decisión sea realmente ineludible.
         */
        dismissible?: boolean;
    }>(),
    { size: 'md', dismissible: true },
);

const open = defineModel<boolean>('open', { default: false });

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-3xl',
};
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-[2px]
                       data-[state=open]:animate-[fade-in_150ms_ease-out]"
            />

            <DialogContent
                :class="[
                    'fixed left-1/2 top-1/2 z-50 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2',
                    'rounded-xl border border-line bg-surface-raised shadow-xl',
                    'data-[state=open]:animate-[modal-in_180ms_cubic-bezier(0.16,1,0.3,1)]',
                    sizes[size],
                ]"
                @escape-key-down="!dismissible && $event.preventDefault()"
                @pointer-down-outside="!dismissible && $event.preventDefault()"
                @interact-outside="!dismissible && $event.preventDefault()"
            >
                <header v-if="title || $slots.header" class="border-b border-line px-5 py-4 pr-12">
                    <slot name="header">
                        <DialogTitle class="font-display text-lg text-fg">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-sm text-fg-muted">
                            {{ description }}
                        </DialogDescription>
                    </slot>
                </header>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-3">
                    <slot name="footer" :close="() => (open = false)" />
                </footer>

                <DialogClose
                    v-if="dismissible"
                    class="absolute right-4 top-4 rounded-md p-1.5 text-fg-subtle transition-colors hover:bg-surface-sunken hover:text-fg"
                    aria-label="Cerrar"
                >
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style>
@keyframes fade-in {
    from {
        opacity: 0;
    }
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translate(-50%, -46%) scale(0.97);
    }
}
</style>
