<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import { useSesion } from '@/composables/useSesion';

const page = usePage();
const sidebarOpen = ref(false);

const usuario = computed(() => (page.props.auth as any)?.usuario ?? null);
const configSesion = computed(() => (page.props.sesion as any) ?? null);

/* ---- Aviso de cierre por inactividad (manual §4.4.4) ---- */

const sesion = useSesion(
    configSesion.value?.minutosInactividad ?? 5,
    configSesion.value?.segundosAviso ?? 60,
);

onMounted(() => {
    if (usuario.value) sesion.iniciar();
});

/** Fecha y hora del ingreso ANTERIOR, que el manual §1.4 obliga a mostrar. */
const ultimoAcceso = computed(() => {
    const iso = usuario.value?.ultimoAcceso;
    if (!iso) return null;

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Mexico_City',
    }).format(new Date(iso));
});

function salir(): void {
    router.post('/logout');
}

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

/**
 * Menú del portal. Sólo se listan destinos que YA existen: un enlace a una
 * ruta sin implementar es peor que no tener el enlace.
 *
 * Faltan por llegar, con sus fases: Documentos y Calendario (Fase 3),
 * Perfil (Fase 4), Mis inversiones a detalle (Fase 5).
 */
const nav = computed(() => {
    const items = [
        { label: 'Panel', href: '/', icon: 'M3 10.5 10 4l7 6.5V16a1 1 0 0 1-1 1h-3v-4H8v4H5a1 1 0 0 1-1-1v-5.5Z' },
        { label: 'Proyectos', href: '/proyectos', icon: 'M3 6h14M3 10h14M3 14h9' },
        { label: 'Calendario', href: '/calendario', icon: 'M4 6h12v10H4V6Zm0 3h12M7 4v3m6-3v3' },
        { label: 'Documentos', href: '/documentos', icon: 'M6 3h5l3 3v11H6V3Zm5 0v3h3' },
        { label: 'Mi perfil', href: '/perfil', icon: 'M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-6 6a6 6 0 0 1 12 0' },
    ];

    // El catálogo del design system no es parte del producto; se muestra
    // sólo mientras dura la migración.
    items.push({
        label: 'Componentes',
        href: '/ui',
        icon: 'M4 4h5v5H4V4Zm7 0h5v5h-5V4ZM4 11h5v5H4v-5Zm7 0h5v5h-5v-5Z',
    });

    return items;
});
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

                <!--
                    Identidad del cliente y fecha del ingreso anterior.
                    No es adorno: el manual §1.4 obliga a mostrar ambos de
                    forma notoria al iniciar sesion, para que el cliente pueda
                    detectar un acceso que no reconozca.
                -->
                <div v-if="usuario" class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-fg">
                        {{ usuario.nombreCompleto || usuario.nombre }}
                    </p>
                    <p v-if="ultimoAcceso" class="truncate text-[11px] text-fg-subtle">
                        Último ingreso: {{ ultimoAcceso }}
                    </p>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <ThemeToggle />

                    <Button v-if="usuario" variant="ghost" size="sm" @click="salir">
                        Salir
                    </Button>
                </div>
            </header>

            <!--
                Aviso previo al cierre por inactividad. El cierre real lo
                aplica el servidor; esto solo le da al usuario la oportunidad
                de continuar antes de perder lo que este haciendo.
            -->
            <Modal
                v-if="usuario"
                :open="sesion.avisando.value"
                :dismissible="false"
                size="sm"
                title="¿Sigues ahí?"
            >
                <p class="text-sm text-fg-muted">
                    Por tu seguridad, tu sesión se cerrará por inactividad en
                    <span class="font-semibold tabular-nums text-fg">{{ sesion.reloj.value }}</span>.
                </p>

                <template #footer>
                    <Button variant="secondary" @click="salir">Cerrar sesión</Button>
                    <Button @click="sesion.renovar">No cerrar sesión</Button>
                </template>
            </Modal>

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
