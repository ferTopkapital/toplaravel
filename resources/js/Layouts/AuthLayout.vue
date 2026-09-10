<script setup lang="ts">
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
</script>

<template>
    <div class="relative min-h-dvh">
        <!--
            Fondo de marca. Se pinta con el navy en ambos temas: es la pantalla
            donde el cliente verifica que esta en la plataforma legitima, asi
            que la identidad visual tiene que ser inequivoca.
        -->
        <div class="absolute inset-0 -z-10 bg-navy-900">
            <div
                class="absolute inset-0 opacity-40"
                style="background: radial-gradient(60rem 40rem at 70% -10%, #df591d33, transparent 60%)"
            />
        </div>

        <div class="absolute right-4 top-4">
            <ThemeToggle />
        </div>

        <div class="flex min-h-dvh items-center justify-center p-4">
            <div class="w-full max-w-md">
                <div class="mb-6 flex items-center justify-center gap-2.5">
                    <span class="inline-block size-3 rounded-full bg-brand-500" aria-hidden="true" />
                    <span class="font-display text-2xl text-white">Top Kapital</span>
                </div>

                <div class="rounded-2xl border border-line bg-surface-raised p-6 shadow-2xl sm:p-8">
                    <slot />
                </div>

                <!--
                    Aviso obligatorio (manual §1.1.1). No es texto de relleno:
                    es informacion que la institucion debe revelar.
                -->
                <p class="mt-6 text-center text-[11px] leading-relaxed text-navy-300">
                    Institución de Financiamiento Colectivo regulada, supervisada y autorizada
                    por las autoridades financieras mexicanas.
                    <br >
                    Autorización CNBV 311-12536446/2022.
                </p>
            </div>
        </div>
    </div>
</template>
