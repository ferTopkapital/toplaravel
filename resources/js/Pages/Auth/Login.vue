<script setup lang="ts">
/**
 * Inicio de sesión en dos pasos.
 *
 * El paso intermedio existe por el manual §1.4: el cliente ve su imagen de
 * seguridad ANTES de capturar la contraseña, para verificar que está en la
 * plataforma legítima y no en un sitio que la suplanta.
 */
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Alert from '@/Components/ui/Alert.vue';

defineOptions({ layout: AuthLayout });

const props = withDefaults(
    defineProps<{
        paso: 'identificador' | 'credenciales';
        email?: string;
        nombre?: string;
        imagenSeguridad?: string | null;
        requiereOtp?: boolean;
        segundosOtp?: number;
        intentosRestantes?: number;
    }>(),
    { requiereOtp: false, segundosOtp: 0, intentosRestantes: 10 },
);

const page = usePage();

/**
 * Motivo por el que se cerró la sesión anterior, si aplica.
 *
 * El manual §4.4.4 obliga a informar al cliente POR QUÉ se le cerró la sesión
 * —inactividad, o acceso desde otro dispositivo—, no sólo a devolverlo aquí.
 */
const motivoCierre = computed(() => (page.props.flash as any)?.error ?? null);

const formIdentificador = useForm({ email: '' });
const formCredenciales = useForm({ password: '', codigo: '' });

function identificar(): void {
    formIdentificador.post('/login/identificar');
}

function entrar(): void {
    formCredenciales.post('/login', {
        onFinish: () => formCredenciales.reset('password', 'codigo'),
    });
}

/* ---- Cuenta regresiva del OTP (vigencia de 2 minutos, manual §4.4) ---- */

const restan = ref(props.segundosOtp);
let intervalo: ReturnType<typeof setInterval> | undefined;

watch(
    () => props.segundosOtp,
    (n) => {
        restan.value = n;
        clearInterval(intervalo);

        if (n > 0) {
            intervalo = setInterval(() => {
                restan.value = Math.max(0, restan.value - 1);
                if (restan.value === 0) clearInterval(intervalo);
            }, 1000);
        }
    },
    { immediate: true },
);

onUnmounted(() => clearInterval(intervalo));

const relojOtp = computed(() => {
    const m = Math.floor(restan.value / 60);
    const s = restan.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});
</script>

<template>
    <Head title="Iniciar sesión" />

    <Alert v-if="motivoCierre" tone="warn" title="Tu sesión se cerró" class="mb-5">
        {{ motivoCierre }}
    </Alert>

    <!-- ---------- Paso 1: identificador ---------- -->
    <form v-if="paso === 'identificador'" class="space-y-5" @submit.prevent="identificar">
        <div>
            <h1 class="font-display text-xl text-fg">Iniciar sesión</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Captura tu correo para continuar.
            </p>
        </div>

        <Input
            id="email"
            v-model="formIdentificador.email"
            label="Correo electrónico"
            type="email"
            placeholder="tu@correo.com"
            required
            :error="formIdentificador.errors.email"
        />

        <Button type="submit" block :loading="formIdentificador.processing">
            Continuar
        </Button>
    </form>

    <!-- ---------- Paso 2: imagen de seguridad + credenciales ---------- -->
    <form v-else class="space-y-5" @submit.prevent="entrar">
        <div>
            <h1 class="font-display text-xl text-fg">
                Hola{{ nombre ? `, ${nombre}` : '' }}
            </h1>
            <p class="mt-1 text-sm text-fg-muted">{{ email }}</p>
        </div>

        <!--
            Imagen de seguridad. Si el usuario aun no eligio una NO se inventa
            ninguna: mostrar una imagen equivocada destruiria justamente la
            garantia que este control da.
        -->
        <div
            v-if="imagenSeguridad"
            class="flex items-center gap-3 rounded-xl border border-line bg-surface-sunken p-3"
        >
            <img
                :src="imagenSeguridad"
                alt="Tu imagen de seguridad"
                class="size-16 shrink-0 rounded-lg object-cover"
            >
            <div class="min-w-0">
                <p class="text-sm font-semibold text-fg">Tu imagen de seguridad</p>
                <p class="mt-0.5 text-xs text-fg-muted">
                    Si no es la que elegiste, no captures tu contraseña y comunícate
                    con soporte.
                </p>
            </div>
        </div>

        <Alert v-else tone="warn" title="Sin imagen de seguridad">
            Aún no has elegido tu imagen de seguridad. Podrás hacerlo desde tu perfil
            al entrar.
        </Alert>

        <Input
            id="password"
            v-model="formCredenciales.password"
            label="Contraseña"
            type="password"
            required
            :error="formCredenciales.errors.password"
        />

        <template v-if="requiereOtp">
            <Alert tone="info" title="Verificación adicional">
                Por seguridad, enviamos un código de 8 caracteres a tu correo.
            </Alert>

            <Input
                id="codigo"
                v-model="formCredenciales.codigo"
                label="Código de verificación"
                placeholder="8 caracteres"
                required
                :error="formCredenciales.errors.codigo"
                :hint="restan > 0 ? `El código expira en ${relojOtp}` : 'El código expiró. Vuelve a empezar para recibir uno nuevo.'"
            />
        </template>

        <p v-else-if="intentosRestantes < 10" class="text-xs text-warn-500">
            Te quedan {{ intentosRestantes }} intentos antes de que se te pida un
            código de verificación.
        </p>

        <Button type="submit" block :loading="formCredenciales.processing">
            Entrar
        </Button>

        <Button href="/login" variant="ghost" size="sm" block>
            Usar otro correo
        </Button>
    </form>
</template>
