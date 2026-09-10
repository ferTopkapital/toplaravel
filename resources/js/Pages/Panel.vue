<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';

defineProps<{
    /** Conteos leidos de la base compartida, para comprobar la conexion. */
    stats: { label: string; value: number }[];
    laravel: string;
    php: string;
}>();
</script>

<template>
    <Head title="Panel" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl text-fg">Migración a Laravel</h1>
                <p class="mt-1 text-sm text-fg-muted">
                    Fase 0 — fundación. La app ya lee la base de datos compartida.
                </p>
            </div>
            <Badge tone="success">Conectado a <code class="ml-1">topkapital</code></Badge>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="stat in stats" :key="stat.label">
                <p class="text-sm text-fg-muted">{{ stat.label }}</p>
                <p class="mt-1 font-display text-3xl text-fg">{{ stat.value.toLocaleString('es-MX') }}</p>
            </Card>
        </div>

        <Card title="Entorno">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-4 border-b border-line pb-2">
                    <dt class="text-fg-muted">Laravel</dt>
                    <dd class="font-medium text-fg">{{ laravel }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-line pb-2">
                    <dt class="text-fg-muted">PHP</dt>
                    <dd class="font-medium text-fg">{{ php }}</dd>
                </div>
            </dl>

            <template #footer>
                <Button href="/ui" variant="secondary" size="sm">Ver design system</Button>
            </template>
        </Card>
    </div>
</template>
