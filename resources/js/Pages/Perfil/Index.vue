<script setup lang="ts">
/**
 * Perfil del inversionista (manual §1.1.2).
 *
 * Las cuentas bancarias son de SÓLO LECTURA a propósito: la cuenta se
 * identifica sola al recibir la primera transferencia por STP. Un formulario
 * de alta aquí rompería ese control.
 */
import { computed, ref } from 'vue';
import { Head, Deferred, router, usePage } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { fecha } from '@/Components/MoneyFormat';

const props = defineProps<{
    general: Record<string, any>;
    imagenSeguridad: number;
    imagenesDisponibles: number;
    beneficiarios?: { id: number; nombre: string; parentesco: string | null; porcentaje: number | null }[];
    cuentas?: { id: number; titular: string; clabe: string; confirmada: boolean; predeterminada: boolean }[];
}>();

const page = usePage();
const aviso = computed(() => (page.props.flash as any)?.success ?? null);

const elegida = ref(props.imagenSeguridad || 0);
const guardando = ref(false);

const imagenes = computed(() =>
    Array.from({ length: props.imagenesDisponibles }, (_, i) => ({
        id: i + 1,
        src: `/images/seguridad/img-${String(i + 1).padStart(2, '0')}.jpg`,
    })),
);

function guardarImagen(): void {
    router.post('/perfil/imagen', { imagen: elegida.value }, {
        preserveScroll: true,
        onStart: () => (guardando.value = true),
        onFinish: () => (guardando.value = false),
    });
}

const campos = computed(() => [
    { label: 'Nombre completo', valor: props.general.nombreCompleto },
    { label: 'Correo electrónico', valor: props.general.email },
    { label: 'RFC', valor: props.general.rfc },
    { label: 'CURP', valor: props.general.curp },
    { label: 'Teléfono', valor: props.general.telefono },
    { label: 'Fecha de nacimiento', valor: props.general.fechaNacimiento ? fecha(props.general.fechaNacimiento) : null },
    { label: 'Nacionalidad', valor: props.general.nacionalidad },
    { label: 'Ocupación', valor: props.general.profesion },
    { label: 'Régimen fiscal', valor: props.general.regimenFiscal },
    { label: 'Código postal', valor: props.general.codigoPostal },
]);
</script>

<template>
    <Head title="Mi perfil" />

    <div class="mx-auto max-w-4xl space-y-6">
        <h1 class="text-2xl text-fg">Mi perfil</h1>

        <Alert v-if="aviso" tone="success">{{ aviso }}</Alert>

        <!-- Imagen de seguridad (manual §1.4) -->
        <Card
            title="Imagen de seguridad"
            subtitle="La verás al iniciar sesión, antes de capturar tu contraseña. Si algún día no aparece o es otra, no captures tus datos."
        >
            <div class="grid grid-cols-4 gap-3 sm:grid-cols-8">
                <button
                    v-for="img in imagenes"
                    :key="img.id"
                    type="button"
                    :aria-pressed="elegida === img.id"
                    :aria-label="`Imagen ${img.id}`"
                    :class="[
                        'aspect-square overflow-hidden rounded-lg border-2 transition-all duration-150',
                        elegida === img.id
                            ? 'border-accent ring-2 ring-accent/30'
                            : 'border-transparent opacity-70 hover:opacity-100',
                    ]"
                    @click="elegida = img.id"
                >
                    <img :src="img.src" alt="" loading="lazy" class="size-full object-cover">
                </button>
            </div>

            <template #footer>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-fg-subtle">
                        {{ imagenSeguridad ? 'Puedes cambiarla cuando quieras.' : 'Aún no has elegido una.' }}
                    </p>
                    <Button
                        size="sm"
                        :disabled="!elegida || elegida === imagenSeguridad"
                        :loading="guardando"
                        @click="guardarImagen"
                    >
                        Guardar
                    </Button>
                </div>
            </template>
        </Card>

        <Card title="Información general">
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                <div v-for="c in campos" :key="c.label" class="flex justify-between gap-3 border-b border-line pb-2">
                    <dt class="text-sm text-fg-muted">{{ c.label }}</dt>
                    <dd class="text-right text-sm font-medium text-fg">{{ c.valor || '—' }}</dd>
                </div>
            </dl>

            <template #footer>
                <p class="text-xs text-fg-subtle">
                    La edición de estos datos llega con el onboarding (fase 4).
                </p>
            </template>
        </Card>

        <Card title="Beneficiarios">
            <Deferred data="beneficiarios">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 2" :key="n" class="h-10" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="beneficiarios && beneficiarios.length" class="divide-y divide-line">
                    <div
                        v-for="b in beneficiarios"
                        :key="b.id"
                        class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="font-medium text-fg">{{ b.nombre }}</p>
                            <p v-if="b.parentesco" class="text-xs text-fg-subtle">{{ b.parentesco }}</p>
                        </div>
                        <Badge v-if="b.porcentaje !== null">{{ b.porcentaje }}%</Badge>
                    </div>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    No has registrado beneficiarios. Puedes hacerlo en cualquier momento.
                </p>
            </Deferred>
        </Card>

        <Card title="Cuentas bancarias">
            <Alert tone="info" class="mb-4">
                Tu cuenta se identifica <strong>automáticamente</strong> al recibir tu primera
                transferencia, validando titular, RFC, monto y referencia. Por eso no se
                captura a mano. Tus rendimientos se depositan siempre en esa cuenta.
            </Alert>

            <Deferred data="cuentas">
                <template #fallback>
                    <Skeleton class="h-12" rounded="rounded-lg" />
                </template>

                <div v-if="cuentas && cuentas.length" class="divide-y divide-line">
                    <div
                        v-for="c in cuentas"
                        :key="c.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="font-medium text-fg">{{ c.titular || 'Titular no registrado' }}</p>
                            <p class="font-mono text-xs text-fg-subtle">{{ c.clabe }}</p>
                        </div>
                        <div class="flex gap-2">
                            <Badge v-if="c.predeterminada" tone="accent">Principal</Badge>
                            <Badge :tone="c.confirmada ? 'success' : 'warn'">
                                {{ c.confirmada ? 'Confirmada' : 'Por confirmar' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Aún no hay una cuenta identificada. Se registrará con tu primera inversión.
                </p>
            </Deferred>
        </Card>
    </div>
</template>
