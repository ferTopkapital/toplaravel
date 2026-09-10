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
import Modal from '@/Components/ui/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Stepper, { type Paso } from '@/Components/wizard/Stepper.vue';

const banco = ref<string>();
const monto = ref('');
const cargando = ref(false);
const modalAbierto = ref(false);
const acepta = ref(false);
const notas = ref('');
const inversion = ref<number>();
const pasoActual = ref(2);

// Los pasos reales del onboarding del inversionista (frontend/views/usuario).
const pasos: Paso[] = [
    { id: 1, label: 'Información', hint: 'Datos generales' },
    { id: 2, label: 'Beneficiarios' },
    { id: 3, label: 'Archivos', hint: 'Sólo KYC nivel 2' },
    { id: 4, label: 'Constancia' },
    { id: 5, label: 'Contrato', hint: 'Firma digitalizada' },
];

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

        <Card title="Formulario" subtitle="Campo de moneda en es-MX, casilla y área de texto.">
            <div class="grid gap-4 sm:grid-cols-2">
                <MoneyInput
                    id="inversion"
                    v-model="inversion"
                    label="Monto a invertir"
                    hint="Se edita en crudo y se formatea al salir del campo."
                />

                <div class="flex items-end">
                    <p class="text-xs text-fg-subtle">
                        Valor en el modelo:
                        <code class="text-fg">{{ inversion ?? '—' }}</code>
                        <span class="block">(número, no la cadena formateada)</span>
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <Textarea
                        id="notas"
                        v-model="notas"
                        label="Notas"
                        placeholder="Observaciones del expediente…"
                        :rows="3"
                    />
                </div>

                <div class="sm:col-span-2">
                    <Checkbox id="acepta" v-model="acepta">
                        Confirmo que la información de la operación es correcta.
                    </Checkbox>
                </div>
            </div>
        </Card>

        <Card title="Pasos del wizard" subtitle="Uno solo para los cinco wizards de la app.">
            <Stepper :pasos="pasos" :actual="pasoActual" @ir="pasoActual = $event" />

            <template #footer>
                <div class="flex justify-between">
                    <Button
                        variant="secondary"
                        size="sm"
                        :disabled="pasoActual === 0"
                        @click="pasoActual--"
                    >
                        Anterior
                    </Button>
                    <Button size="sm" :disabled="pasoActual === pasos.length - 1" @click="pasoActual++">
                        Siguiente
                    </Button>
                </div>
            </template>
        </Card>

        <Card title="Avisos">
            <div class="space-y-3">
                <Alert tone="info" title="Cuenta bancaria">
                    Se identificará automáticamente al recibir tu transferencia por STP.
                </Alert>
                <Alert tone="success" title="Perfil aprobado">
                    Tu documentación fue revisada y ya puedes invertir.
                </Alert>
                <Alert tone="warn" title="Completa tu KYC">
                    Tu inversión supera los $5,000 MXN de este mes calendario.
                </Alert>
                <Alert tone="danger" title="Código expirado">
                    El código de verificación tiene una vigencia de 2 minutos.
                </Alert>
            </div>
        </Card>

        <Card title="Modal" subtitle="Con trampa de foco, cierre con Escape y bloqueo del scroll.">
            <Button variant="secondary" @click="modalAbierto = true">Abrir modal</Button>

            <Modal
                v-model:open="modalAbierto"
                title="Confirmar inversión"
                description="Revisa las características de la operación antes de continuar."
            >
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between border-b border-line pb-2">
                        <dt class="text-fg-muted">Proyecto</dt>
                        <dd class="font-medium text-fg">Residencial Altavista</dd>
                    </div>
                    <div class="flex justify-between border-b border-line pb-2">
                        <dt class="text-fg-muted">Monto</dt>
                        <dd class="font-medium tabular-nums text-fg">$25,000.00 MXN</dd>
                    </div>
                    <div class="flex justify-between border-b border-line pb-2">
                        <dt class="text-fg-muted">Tasa</dt>
                        <dd class="font-medium tabular-nums text-fg">16.5% anual</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-fg-muted">Plazo</dt>
                        <dd class="font-medium text-fg">18 meses</dd>
                    </div>
                </dl>

                <template #footer="{ close }">
                    <Button variant="secondary" @click="close">Cancelar</Button>
                    <Button @click="close">Continuar</Button>
                </template>
            </Modal>
        </Card>

        <Card title="Tabla de datos">
            <p class="text-sm text-fg-muted">
                El reemplazo del GridView vive en su propia página, con datos reales de la
                base compartida.
            </p>
            <template #footer>
                <Button href="/ui/tabla" variant="secondary" size="sm">Ver la tabla</Button>
            </template>
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
