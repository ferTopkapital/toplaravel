<script setup lang="ts">
/**
 * Panel del inversionista (manual §1.1.2).
 *
 * El resumen viaja con la primera respuesta y las tres secciones de abajo
 * llegan diferidas (`Inertia::defer`). Por eso hay skeletons: la pantalla se
 * pinta de inmediato y el contenido pesado entra después, sin que el usuario
 * se quede mirando un spinner en blanco.
 */
import { Head, Deferred, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import ProyectoCard from '@/Components/ProyectoCard.vue';
import { dinero, fecha, porcentaje } from '@/Components/MoneyFormat';

type Inversion = {
    id: number;
    monto: number;
    fecha: string | null;
    proyecto: string;
    proyectoId: number;
    etapa: string;
    tasa: number | null;
    plazo: number | null;
};

defineProps<{
    resumen: { capitalInvertido: number; proyectos: number; pendientes: number };
    inversiones?: Inversion[];
    noticias?: { id: number; titulo: string; proyecto: string | null; fecha: string | null; pdf: string | null }[];
    oportunidades?: any[];
}>();
</script>

<template>
    <Head title="Panel" />

    <div class="space-y-6">
        <h1 class="text-2xl text-fg">Mi panel</h1>

        <!-- Resumen: llega con la primera respuesta, sin espera. -->
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <p class="text-sm text-fg-muted">Capital invertido</p>
                <p class="mt-1 font-display text-3xl tabular-nums text-fg">
                    {{ dinero(resumen.capitalInvertido) }}
                </p>
            </Card>

            <Card>
                <p class="text-sm text-fg-muted">Proyectos</p>
                <p class="mt-1 font-display text-3xl tabular-nums text-fg">{{ resumen.proyectos }}</p>
            </Card>

            <Card>
                <p class="text-sm text-fg-muted">Pendientes de depósito</p>
                <p class="mt-1 font-display text-3xl tabular-nums text-fg">{{ resumen.pendientes }}</p>
            </Card>
        </div>

        <!-- Mis inversiones -->
        <Card title="Mis inversiones">
            <Deferred data="inversiones">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 3" :key="n" class="h-14" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="inversiones && inversiones.length" class="divide-y divide-line">
                    <Link
                        v-for="i in inversiones"
                        :key="i.id"
                        :href="`/proyectos/${i.proyectoId}`"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0
                               transition-colors hover:bg-surface-sunken"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-fg">{{ i.proyecto }}</p>
                            <p class="mt-0.5 text-xs text-fg-subtle">
                                {{ fecha(i.fecha) }}
                                <span v-if="i.tasa"> · {{ porcentaje(i.tasa) }} anual</span>
                                <span v-if="i.plazo"> · {{ i.plazo }} meses</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Badge>{{ i.etapa }}</Badge>
                            <span class="font-semibold tabular-nums text-fg">{{ dinero(i.monto) }}</span>
                        </div>
                    </Link>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Todavía no tienes inversiones.
                </p>
            </Deferred>
        </Card>

        <!-- Oportunidades abiertas -->
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg text-fg">Proyectos en fondeo</h2>
                <Button href="/proyectos" variant="ghost" size="sm">Ver todos</Button>
            </div>

            <Deferred data="oportunidades">
                <template #fallback>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Skeleton v-for="n in 3" :key="n" class="h-72" rounded="rounded-xl" />
                    </div>
                </template>

                <div v-if="oportunidades && oportunidades.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <ProyectoCard
                        v-for="p in oportunidades"
                        :key="p.proyectoId"
                        v-bind="p"
                        :direccion="null"
                        :objetivo="p.objetivo ?? 0"
                        :fondeado="p.fondeado ?? 0"
                        :en-fondeo="true"
                    />
                </div>

                <Card v-else>
                    <p class="py-2 text-center text-sm text-fg-subtle">
                        No hay proyectos en fondeo en este momento.
                    </p>
                </Card>
            </Deferred>
        </div>

        <!-- Noticias de sus proyectos -->
        <Card title="Novedades de tus proyectos">
            <Deferred data="noticias">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 2" :key="n" class="h-10" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="noticias && noticias.length" class="divide-y divide-line">
                    <div v-for="n in noticias" :key="n.id" class="py-3 first:pt-0 last:pb-0">
                        <p class="font-medium text-fg">{{ n.titulo }}</p>
                        <p class="mt-0.5 text-xs text-fg-subtle">
                            {{ n.proyecto }} · {{ fecha(n.fecha) }}
                        </p>
                        <a
                            v-if="n.pdf"
                            :href="n.pdf"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-block text-xs font-medium text-accent"
                        >Ver documento</a>
                    </div>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Aquí verás los avances de los proyectos en los que inviertas.
                </p>
            </Deferred>
        </Card>
    </div>
</template>
