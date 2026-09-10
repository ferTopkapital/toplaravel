<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

const page = usePage();
const sidebarOpen = ref(false);

/**
 * Clave de la transicion de pagina: SOLO la ruta, sin query string.
 *
 * Si se usara `page.url` completo, cada orden, filtro o cambio de pagina de
 * una tabla cambiaria la clave y remontaria la pagina entera — perdiendo el
 * foco del buscador, el estado del componente y cualquier ganancia de la
 * recarga parcial. Con la ruta sola, esas interacciones no animan nada
 * (que es lo correcto: el usuario no cambio de pantalla) y solo se anima al
 * navegar de verdad.
 */
const claveRuta = computed(() => page.url.split('?')[0]);

// Provisional: refleja el menu de la app Yii2 (frontend/views/layouts/partials/menu.php).
// Se ira poblando conforme avancen las fases 3 a 5 del plan de migracion.
const nav = [
    { label: 'Panel', href: '/', icon: 'M3 10.5 10 4l7 6.5V16a1 1 0 0 1-1 1h-3v-4H8v4H5a1 1 0 0 1-1-1v-5.5Z' },
    { label: 'Proyectos', href: '/proyectos', icon: 'M3 6h14M3 10h14M3 14h9' },
    { label: 'Mis inversiones', href: '/inversiones', icon: 'M4 15V9m4 6V5m4 10v-4m4 4V7' },
    { label: 'Documentos', href: '/documentos', icon: 'M6 3h5l3 3v11H6V3Zm5 0v3h3' },
    { label: 'Componentes', href: '/ui', icon: 'M4 4h5v5H4V4Zm7 0h5v5h-5V4ZM4 11h5v5H4v-5Zm7 0h5v5h-5v-5Z' },
];
</script>

<template>
    <div class="min-h-dvh">
        <!-- Sidebar. En movil se convierte en panel deslizante. -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 w-64 border-r border-line bg-surface-raised',
                'transition-transform duration-200 ease-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 items-center gap-2 border-b border-line px-5">
                <span class="inline-block size-2.5 rounded-full bg-accent" aria-hidden="true" />
                <span class="font-display text-lg text-fg">Top Kapital</span>
            </div>

            <nav class="space-y-1 p-3">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                        claveRuta === item.href
                            ? 'bg-accent/10 text-accent'
                            : 'text-fg-muted hover:bg-surface-sunken hover:text-fg',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <svg class="size-[18px] shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path :d="item.icon" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <!-- Velo del sidebar en movil. -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-navy-950/50 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <div class="lg:pl-64">
            <header
                class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface-raised/80 px-4 backdrop-blur sm:px-6"
            >
                <button
                    type="button"
                    class="-ml-1 rounded-lg p-2 text-fg-muted hover:bg-surface-sunken lg:hidden"
                    aria-label="Abrir menú"
                    @click="sidebarOpen = true"
                >
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="ml-auto">
                    <ThemeToggle />
                </div>
            </header>

            <!--
                Transicion de pagina. La clave es la ruta sin query string
                (ver `claveRuta`): asi solo anima al cambiar de pantalla, no
                al ordenar o paginar una tabla.
            -->
            <main class="p-4 sm:p-6">
                <Transition name="page" mode="out-in">
                    <div :key="claveRuta">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>
    </div>
</template>
