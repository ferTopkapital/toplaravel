import { computed, onMounted, onUnmounted, ref } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'topkapital.theme';

/**
 * Preferencia elegida por el usuario. Es un ref a nivel de modulo (no dentro
 * del composable) para que todos los componentes que llamen a useTheme()
 * compartan el mismo estado: si el toggle del topbar cambia el tema, el del
 * menu movil se entera.
 */
const preference = ref<ThemePreference>('system');

/** Lo que el sistema operativo pide en este momento. */
const systemPrefersDark = ref(false);

let mediaQuery: MediaQueryList | null = null;

function readStoredPreference(): ThemePreference {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
    } catch {
        // Modo incognito o cookies bloqueadas: se cae a "system".
    }

    return 'system';
}

function applyToDocument(dark: boolean): void {
    document.documentElement.classList.toggle('dark', dark);
    // Hace que los controles nativos (scrollbars, inputs de fecha) sigan el tema.
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

export function useTheme() {
    const isDark = computed(() =>
        preference.value === 'system' ? systemPrefersDark.value : preference.value === 'dark',
    );

    function setTheme(next: ThemePreference): void {
        preference.value = next;

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Si no se puede guardar, el tema igual aplica en esta sesion.
        }

        applyToDocument(isDark.value);
    }

    function onSystemChange(event: MediaQueryListEvent): void {
        systemPrefersDark.value = event.matches;

        // Solo repinta si el usuario dejo la decision al sistema.
        if (preference.value === 'system') {
            applyToDocument(isDark.value);
        }
    }

    onMounted(() => {
        mediaQuery ??= window.matchMedia('(prefers-color-scheme: dark)');
        systemPrefersDark.value = mediaQuery.matches;
        preference.value = readStoredPreference();

        applyToDocument(isDark.value);
        mediaQuery.addEventListener('change', onSystemChange);
    });

    onUnmounted(() => {
        mediaQuery?.removeEventListener('change', onSystemChange);
    });

    return { preference, isDark, setTheme };
}
