<script setup lang="ts">
/**
 * Calendario de pagos (manual §1.1.2).
 *
 * Los importes vienen calculados del servidor tal como están guardados. Aquí
 * sólo se formatean: recalcular en el navegador lo que el cliente ya vio en
 * un CFDI timbrado sería crear una discrepancia fiscal.
 */
import { Head, Deferred } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { dinero, fecha } from '@/Components/MoneyFormat';

defineProps<{
    proximos?: any[];
    pagados?: any[];
    totales?: { interesCobrado: number; isrRetenido: number; netoRecibido: number; pagos: number };
}>();
</script>

<template>
    <Head title="Calendario de pagos" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl text-fg">Calendario de pagos</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Rendimientos programados y pagos que ya recibiste.
            </p>
        </div>

        <Deferred data="totales">
            <template #fallback>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-24" rounded="rounded-xl" />
                </div>
            </template>

            <div v-if="totales" class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <p class="text-sm text-fg-muted">Intereses cobrados</p>
                    <p class="mt-1 font-display text-2xl tabular-nums text-fg">
                        {{ dinero(totales.interesCobrado) }}
                    </p>
                </Card>
                <Card>
                    <p class="text-sm text-fg-muted">ISR retenido</p>
                    <p class="mt-1 font-display text-2xl tabular-nums text-fg">
                        {{ dinero(totales.isrRetenido) }}
                    </p>
                </Card>
                <Card>
                    <p class="text-sm text-fg-muted">Neto recibido</p>
                    <p class="mt-1 font-display text-2xl tabular-nums text-accent">
                        {{ dinero(totales.netoRecibido) }}
                    </p>
                    <p class="mt-0.5 text-xs text-fg-subtle">{{ totales.pagos }} pagos</p>
                </Card>
            </div>
        </Deferred>

        <Card title="Próximos pagos">
            <Deferred data="proximos">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 3" :key="n" class="h-12" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="proximos && proximos.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs uppercase tracking-wide text-fg-muted">
                                <th scope="col" class="py-2 text-left font-semibold">Fecha</th>
                                <th scope="col" class="py-2 text-left font-semibold">Proyecto</th>
                                <th scope="col" class="py-2 text-right font-semibold">Capital</th>
                                <th scope="col" class="py-2 text-right font-semibold">Interés</th>
                                <th scope="col" class="py-2 text-right font-semibold">Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in proximos" :key="p.id" class="border-b border-line last:border-0">
                                <td class="py-2.5 text-fg">{{ fecha(p.fecha) }}</td>
                                <td class="py-2.5 text-fg-muted">{{ p.proyecto ?? '—' }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg">{{ dinero(p.capital) }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg">{{ dinero(p.interes) }}</td>
                                <td class="py-2.5 text-right font-semibold tabular-nums text-fg">{{ dinero(p.neto) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    No tienes pagos programados.
                </p>
            </Deferred>
        </Card>

        <Card title="Pagos recibidos">
            <Deferred data="pagados">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 3" :key="n" class="h-12" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="pagados && pagados.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs uppercase tracking-wide text-fg-muted">
                                <th scope="col" class="py-2 text-left font-semibold">Fecha</th>
                                <th scope="col" class="py-2 text-left font-semibold">Proyecto</th>
                                <th scope="col" class="py-2 text-right font-semibold">Interés</th>
                                <th scope="col" class="py-2 text-right font-semibold">ISR</th>
                                <th scope="col" class="py-2 text-right font-semibold">Neto</th>
                                <th scope="col" class="py-2 text-right font-semibold">Factura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in pagados" :key="p.id" class="border-b border-line last:border-0">
                                <td class="py-2.5 text-fg">{{ fecha(p.fecha) }}</td>
                                <td class="py-2.5 text-fg-muted">{{ p.proyecto ?? '—' }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg">{{ dinero(p.interes) }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg-muted">-{{ dinero(p.retencionIsr) }}</td>
                                <td class="py-2.5 text-right font-semibold tabular-nums text-fg">{{ dinero(p.neto) }}</td>
                                <td class="py-2.5 text-right">
                                    <a
                                        v-if="p.cfdi"
                                        :href="`/documentos/cfdi/${p.id}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs font-medium text-accent"
                                    >Descargar</a>
                                    <Badge v-else>Sin factura</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Todavía no has recibido pagos.
                </p>
            </Deferred>
        </Card>
    </div>
</template>
