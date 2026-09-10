import { computed, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Cuenta regresiva de la sesión.
 *
 * Esto NO es el control de inactividad: el control se aplica en el servidor
 * (`ControlDeSesion`). Aquí sólo se avisa al usuario antes de que se le cierre
 * la sesión y se le da la opción de seguir conectado, como pide el manual
 * §4.4.4 ("informar al Cliente del motivo").
 *
 * El sondeo al servidor es deliberadamente espaciado y la ruta que consulta
 * está excluida de renovar la actividad: si preguntar por el estado renovara
 * el reloj, una pestaña abierta viviría para siempre.
 */
export function useSesion(minutosInactividad: number, segundosAviso: number) {
    const restan = ref(minutosInactividad * 60);
    const avisando = computed(() => restan.value > 0 && restan.value <= segundosAviso);

    let tictac: ReturnType<typeof setInterval> | undefined;
    let sondeo: ReturnType<typeof setInterval> | undefined;

    /** Cada segundo se descuenta localmente, para que el reloj se vea fluido. */
    function arrancarTictac(): void {
        clearInterval(tictac);

        tictac = setInterval(() => {
            restan.value = Math.max(0, restan.value - 1);

            if (restan.value === 0) {
                detener();
                // Una petición basta: el servidor ya cerró la sesión y
                // responde con la redirección al login y su motivo.
                router.reload();
            }
        }, 1000);
    }

    /**
     * Se resincroniza con el servidor de vez en cuando, porque el reloj local
     * se desfasa: la pestaña en segundo plano ralentiza los timers, y
     * cualquier navegación de Inertia ya renovó la actividad sin que este
     * contador se entere.
     */
    async function sincronizar(): Promise<void> {
        try {
            const r = await fetch('/sesion/estado', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!r.ok) return;

            const datos = await r.json();
            restan.value = datos.restan;
        } catch {
            // Sin red no se hace nada: el servidor manda, y cuando vuelva la
            // conexión la siguiente petición resolverá la situación.
        }
    }

    async function renovar(): Promise<void> {
        try {
            const r = await fetch('/sesion/renovar', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '',
                },
                credentials: 'same-origin',
            });

            if (r.ok) {
                const datos = await r.json();
                restan.value = datos.restan;
            }
        } catch {
            // Ídem.
        }
    }

    function iniciar(): void {
        arrancarTictac();
        sondeo = setInterval(sincronizar, 60_000);

        // Cada navegación de Inertia es actividad real y ya renovó el reloj
        // en el servidor; se refleja de inmediato en el contador.
        router.on('navigate', () => {
            restan.value = minutosInactividad * 60;
        });
    }

    function detener(): void {
        clearInterval(tictac);
        clearInterval(sondeo);
    }

    onUnmounted(detener);

    const reloj = computed(() => {
        const m = Math.floor(restan.value / 60);
        const s = restan.value % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    });

    return { restan, avisando, reloj, iniciar, detener, renovar };
}
