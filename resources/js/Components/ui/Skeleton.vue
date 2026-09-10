<script setup lang="ts">
// Placeholder de carga. Se prefiere sobre un spinner generico porque conserva
// la forma de la pagina y hace que la espera se sienta mas corta.
withDefaults(defineProps<{ rounded?: string }>(), { rounded: 'rounded-md' });
</script>

<template>
    <div class="skeleton" :class="rounded" aria-hidden="true" />
</template>
