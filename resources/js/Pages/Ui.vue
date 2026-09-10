<script setup lang="ts">
/**
 * Catalogo del design system.
 *
 * Es la pantalla de referencia de la Fase 1 del plan: cualquier componente
 * nuevo se agrega aqui primero y se revisa en tema claro Y oscuro antes de
 * usarse en una vista real.
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Badge from '@/Components/ui/Badge.vue';
import Select, { type SelectOption } from '@/Components/ui/Select.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';

const banco = ref<string>();
const monto = ref('');
const cargando = ref(false);

// Muestra de datos reales del catalogo `bancos` para probar el buscador.
const bancos: SelectOption<string>[] = [
    { value: '002', label: 'BANAMEX' },
    { value: '012', label: 'BBVA MEXICO' },
    { value: '014', label: 'SANTANDER' },
    { value: '021', label: 'HSBC' },
    { value: '030', label: 'BAJIO' },
    { value: '036', label: 'INBURSA' },
    { value: '042', label: 'MIFEL' },
    { value: '044', label: 'SCOTIABANK' },
    { value: '058', label: 'BANREGIO' },
    { value: '072', label: 'BANORTE' },
    { value: '646', label: 'STP' },
];

const paleta = [
    { name: 'brand-400', clase: 'bg-brand-400' },
    { name: 'brand-500', clase: 'bg-brand-500' },
    { name: 'brand-600', clase: 'bg-brand-600' },
    { name: 'navy-700', clase: 'bg-navy-700' },
    { name: 'navy-900', clase: 'bg-navy-900' },
    { name: 'sand-400', clase: 'bg-sand-400' },
];

function simularEnvio(): void {
    cargando.value = true;
    setTimeout(() => (cargando.value = false), 1600);
}
</script>

<template>
    <Head title="Componentes" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl text-fg">Design system</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Catálogo de componentes base. Revísalo en tema claro y oscuro con el
                selector del encabezado.
            </p>
        </div>

        <Card title="Botones" subtitle="Cuatro variantes × tres tamaños, con estado de carga integrado.">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="primary">Invertir</Button>
                    <Button variant="secondary">Cancelar</Button>
                    <Button variant="ghost">Ver detalle</Button>
                    <Button variant="danger">Eliminar</Button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button size="sm">Pequeño</Button>
                    <Button size="md">Mediano</Button>
                    <Button size="lg">Grande</Button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button :loading="cargando" @click="simularEnvio">
                        {{ cargando ? 'Enviando…' : 'Simular envío' }}
                    </Button>
                    <Button variant="secondary" disabled>Deshabilitado</Button>
                </div>
            </div>
        </Card>

        <Card title="Select" subtitle="Reemplazo de select2: buscador, teclado y tema oscuro.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="banco" class="mb-1.5 block text-sm font-medium text-fg">Banco</label>
                    <Select id="banco" v-model="banco" :options="bancos" placeholder="Elige un banco" />
                    <p class="mt-2 text-xs text-fg-subtle">
                        Valor: <code>{{ banco ?? '—' }}</code>
                    </p>
                </div>

                <Input
                    id="monto"
                    v-model="monto"
                    label="Monto de inversión"
                    placeholder="$0.00"
                    hint="Mínimo $1,000.00 MXN"
                />
            </div>
        </Card>

        <Card title="Etiquetas de estado">
            <div class="flex flex-wrap gap-2">
                <Badge>Borrador</Badge>
                <Badge tone="accent">En fondeo</Badge>
                <Badge tone="success">Autorizado</Badge>
                <Badge tone="warn">Pendiente PLD</Badge>
                <Badge tone="danger">Rechazado</Badge>
                <Badge tone="info">En construcción</Badge>
            </div>
        </Card>

        <Card title="Estados de carga" subtitle="Skeletons en vez de spinners: conservan la forma de la página.">
            <div class="space-y-3">
                <Skeleton class="h-5 w-1/3" />
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="h-4 w-1/2" />
                <div class="grid gap-3 pt-2 sm:grid-cols-3">
                    <Skeleton class="h-24" rounded="rounded-xl" />
                    <Skeleton class="h-24" rounded="rounded-xl" />
                    <Skeleton class="h-24" rounded="rounded-xl" />
                </div>
            </div>
        </Card>

        <Card title="Paleta">
            <!--
                Las clases van completas y literales: Tailwind escanea el fuente
                como texto, asi que `bg-brand-${n}` no generaria ninguna regla.
            -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="tono in paleta" :key="tono.name" class="space-y-1.5">
                    <div class="h-14 rounded-lg" :class="tono.clase" />
                    <p class="text-xs text-fg-muted">{{ tono.name }}</p>
                </div>
            </div>
        </Card>
    </div>
</template>
