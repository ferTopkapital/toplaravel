<script setup lang="ts">
/**
 * Detalle del proyecto (manual §1.1.2).
 *
 * Las condiciones (objetivo, tasa, plazo) se leen de la CAMPAÑA, no del
 * proyecto: un proyecto puede fondearse en varias vueltas y mostrar las del
 * proyecto daría las de una vuelta anterior.
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Alert from '@/Components/ui/Alert.vue';
import { dinero, fecha, porcentaje } from '@/Components/MoneyFormat';

const props = defineProps<{
    proyecto: {
        id: number;
        nombre: string;
        etapa: string;
        etapaId: number;
        resumen: string | null;
        direccion: string | null;
        imagenes: string[];
    };
    campana: {
        objetivo: number;
        minimoFondeo: number;
        inversionMin: number;
        fondeado: number;
        avance: number;
        tasa: number;
        plazo: number | null;
        diasRestantes: number | null;
        enFondeo: boolean;
        nivelRiesgo: string | null;
    } | null;
    miInversion: number;
}>();

const imagenActiva = ref(0);
</script>

<template>
    <Head :title="proyecto.nombre" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl text-fg">{{ proyecto.nombre }}</h1>
                <p v-if="proyecto.direccion" class="mt-1 text-sm text-fg-muted">
                    {{ proyecto.direccion }}
                </p>
            </div>
            <Badge :tone="campana?.enFondeo ? 'accent' : 'neutral'">{{ proyecto.etapa }}</Badge>
        </div>

        <Alert v-if="miInversion > 0" tone="success" title="Ya inviertes en este proyecto">
            Tienes <strong>{{ dinero(miInversion) }}</strong> invertidos aquí.
        </Alert>

        <!-- Galería -->
        <div v-if="proyecto.imagenes.length" class="space-y-2">
            <div class="aspect-[16/9] overflow-hidden rounded-xl border border-line bg-surface-sunken">
                <img
                    :src="proyecto.imagenes[imagenActiva]"
                    :alt="proyecto.nombre"
                    class="size-full object-cover"
                >
            </div>

            <div v-if="proyecto.imagenes.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="(img, i) in proyecto.imagenes"
                    :key="i"
                    type="button"
                    :aria-label="`Ver imagen ${i + 1}`"
                    :class="[
                        'size-16 shrink-0 overflow-hidden rounded-lg border-2 transition-colors',
                        i === imagenActiva ? 'border-accent' : 'border-transparent opacity-60 hover:opacity-100',
                    ]"
                    @click="imagenActiva = i"
                >
                    <img :src="img" alt="" loading="lazy" class="size-full object-cover">
                </button>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <Card v-if="proyecto.resumen" title="Sobre el proyecto">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-fg-muted">
                        {{ proyecto.resumen }}
                    </p>
                </Card>
            </div>

            <!-- Condiciones de la campaña -->
            <Card v-if="campana" title="Condiciones">
                <div class="space-y-4">
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between text-sm">
                            <span class="font-semibold text-fg">{{ dinero(campana.fondeado) }}</span>
                            <span class="text-xs text-fg-subtle">{{ porcentaje(campana.avance) }}</span>
                        </div>

                        <div
                            class="h-2 overflow-hidden rounded-full bg-surface-sunken"
                            role="progressbar"
                            :aria-valuenow="campana.avance"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-accent transition-[width] duration-700"
                                :style="{ width: `${Math.min(100, campana.avance)}%` }"
                            />
                        </div>

                        <p class="mt-1.5 text-xs text-fg-subtle">
                            Objetivo: {{ dinero(campana.objetivo) }}
                        </p>
                    </div>

                    <dl class="space-y-2.5 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-fg-muted">Rendimiento anual</dt>
                            <dd class="font-semibold tabular-nums text-accent">{{ porcentaje(campana.tasa) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-fg-muted">Plazo</dt>
                            <dd class="font-medium tabular-nums text-fg">{{ campana.plazo ?? '—' }} meses</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-fg-muted">Inversión mínima</dt>
                            <dd class="font-medium tabular-nums text-fg">{{ dinero(campana.inversionMin) }}</dd>
                        </div>
                        <div v-if="campana.nivelRiesgo" class="flex justify-between gap-3">
                            <dt class="text-fg-muted">Nivel de riesgo</dt>
                            <dd class="font-medium text-fg">{{ campana.nivelRiesgo }}</dd>
                        </div>
                        <div v-if="campana.diasRestantes !== null" class="flex justify-between gap-3">
                            <dt class="text-fg-muted">Días restantes</dt>
                            <dd class="font-medium tabular-nums text-fg">{{ campana.diasRestantes }}</dd>
                        </div>
                    </dl>
                </div>

                <template #footer>
                    <!--
                        El flujo de inversion es la Fase 5. Se deja el boton
                        deshabilitado y dicho, en vez de un enlace que no lleva
                        a ningun lado.
                    -->
                    <Button v-if="campana.enFondeo" block disabled>
                        Invertir (disponible en la fase 5)
                    </Button>
                    <p v-else class="text-center text-sm text-fg-subtle">
                        Este proyecto ya no está en periodo de fondeo.
                    </p>
                </template>
            </Card>

            <Card v-else title="Condiciones">
                <p class="text-sm text-fg-subtle">Este proyecto no tiene una campaña activa.</p>
            </Card>
        </div>

        <Button href="/proyectos" variant="secondary" size="sm">Volver a proyectos</Button>
    </div>
</template>
