<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string }>();
</script>

<template>
    <div class="rounded-xl border border-line bg-surface-raised shadow-sm transition-colors">
        <header v-if="title || $slots.header" class="border-b border-line px-5 py-4">
            <slot name="header">
                <h3 class="text-base font-semibold text-fg">{{ title }}</h3>
                <p v-if="subtitle" class="mt-0.5 text-sm text-fg-muted">{{ subtitle }}</p>
            </slot>
        </header>

        <div class="p-5">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="border-t border-line px-5 py-3">
            <slot name="footer" />
        </footer>
    </div>
</template>
