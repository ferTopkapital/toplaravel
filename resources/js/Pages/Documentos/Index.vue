<script setup lang="ts">
/**
 * Documentos del inversionista (manual §1.1.2).
 *
 * Los enlaces apuntan a `/documentos/{tipo}/{id}`, NUNCA a S3 directo: el
 * servidor comprueba primero que el documento sea de quien lo pide y sólo
 * entonces firma una URL de vida corta.
 */
import { Head, Deferred } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { dinero, fecha } from '@/Components/MoneyFormat';

defineProps<{
    fiscales?: any[];
    inversiones?: any[];
}>();
</script>

<template>
    <Head title="Documentos" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl text-fg">Documentos</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Facturas, constancias de retención y comprobantes de tus inversiones.
            </p>
        </div>

        <Card title="Facturas y constancias de retención">
            <Deferred data="fiscales">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 3" :key="n" class="h-12" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="fiscales && fiscales.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs uppercase tracking-wide text-fg-muted">
                                <th scope="col" class="py-2 text-left font-semibold">Fecha</th>
                                <th scope="col" class="py-2 text-left font-semibold">Proyecto</th>
                                <th scope="col" class="py-2 text-right font-semibold">Interés</th>
                                <th scope="col" class="py-2 text-right font-semibold">ISR</th>
                                <th scope="col" class="py-2 text-right font-semibold">Documentos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in fiscales" :key="d.id" class="border-b border-line last:border-0">
                                <td class="py-2.5 text-fg">{{ fecha(d.fecha) }}</td>
                                <td class="py-2.5 text-fg-muted">{{ d.proyecto ?? '—' }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg">{{ dinero(d.interes) }}</td>
                                <td class="py-2.5 text-right tabular-nums text-fg-muted">{{ dinero(d.retencionIsr) }}</td>
                                <td class="py-2.5">
                                    <div class="flex justify-end gap-2">
                                        <a
                                            v-if="d.cfdi"
                                            :href="`/documentos/cfdi/${d.id}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-xs font-medium text-accent"
                                        >Factura</a>
                                        <a
                                            v-if="d.constancia"
                                            :href="`/documentos/constancia-isr/${d.id}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-xs font-medium text-accent"
                                        >Constancia</a>
                                        <Badge v-if="!d.cfdi && !d.constancia">Sin documentos</Badge>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Aquí verás tus facturas de intereses y constancias de retención de ISR
                    cuando recibas tu primer pago.
                </p>
            </Deferred>
        </Card>

        <Card title="Comprobantes de inversión">
            <Deferred data="inversiones">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton v-for="n in 2" :key="n" class="h-12" rounded="rounded-lg" />
                    </div>
                </template>

                <div v-if="inversiones && inversiones.length" class="divide-y divide-line">
                    <div
                        v-for="i in inversiones"
                        :key="i.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-fg">{{ i.proyecto ?? 'Proyecto' }}</p>
                            <p class="mt-0.5 text-xs text-fg-subtle">
                                {{ fecha(i.fecha) }} · {{ dinero(i.monto) }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <a
                                v-if="i.comprobante"
                                :href="`/documentos/comprobante/${i.id}`"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-medium text-accent"
                            >Comprobante</a>
                            <a
                                v-if="i.constancia"
                                :href="`/documentos/constancia/${i.id}`"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-medium text-accent"
                            >Constancia</a>
                            <Badge v-if="!i.comprobante && !i.constancia">Sin documentos</Badge>
                        </div>
                    </div>
                </div>

                <p v-else class="py-6 text-center text-sm text-fg-subtle">
                    Todavía no tienes inversiones.
                </p>
            </Deferred>
        </Card>

        <Alert tone="info" title="Estado de cuenta">
            La descarga de estados de cuenta por periodo requiere un código de verificación
            (manual §4.4.3) y llega junto con el resto del segundo factor.
        </Alert>
    </div>
</template>
