<script setup lang="ts">
/**
 * Restablecimiento de contraseña en tres pasos (manual §4.2.4 y §4.4.2).
 * Es también el camino de desbloqueo cuando se agotaron los 10 intentos.
 */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Alert from '@/Components/ui/Alert.vue';

defineOptions({ layout: AuthLayout });

const props = defineProps<{
    paso: 'solicitar' | 'verificar' | 'restablecer';
    email?: string;
}>();

const formEmail = useForm({ email: '' });
const formCodigo = useForm({ codigo: '' });
const formPassword = useForm({ password: '', password_confirmation: '' });

const vigenciaMinutos = computed(() => 2);

/** Requisitos del manual §4.1, visibles antes de que el usuario falle. */
const requisitos = [
    'Entre 8 y 30 caracteres',
    'Al menos una mayúscula y una minúscula',
    'Al menos un dígito y un carácter especial',
    'Sin tu correo ni el nombre de la institución',
    'Sin más de 3 caracteres iguales o en secuencia seguidos',
];
</script>

<template>
    <Head title="Recuperar contraseña" />

    <!-- ---------- Paso 1 ---------- -->
    <form
        v-if="paso === 'solicitar'"
        class="space-y-5"
        @submit.prevent="formEmail.post('/recuperar')"
    >
        <div>
            <h1 class="font-display text-xl text-fg">Recuperar contraseña</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Te enviaremos un código a tu correo para que puedas establecer una
                contraseña nueva.
            </p>
        </div>

        <Input
            id="email"
            v-model="formEmail.email"
            label="Correo electrónico"
            type="email"
            placeholder="tu@correo.com"
            required
            :error="formEmail.errors.email"
        />

        <Button type="submit" block :loading="formEmail.processing">
            Enviar código
        </Button>

        <Button href="/login" variant="ghost" size="sm" block>
            Volver a iniciar sesión
        </Button>
    </form>

    <!-- ---------- Paso 2 ---------- -->
    <form
        v-else-if="paso === 'verificar'"
        class="space-y-5"
        @submit.prevent="formCodigo.post('/recuperar/verificar')"
    >
        <div>
            <h1 class="font-display text-xl text-fg">Revisa tu correo</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Si <span class="font-medium text-fg">{{ email }}</span> está registrado,
                te enviamos un código de 8 caracteres.
            </p>
        </div>

        <Input
            id="codigo"
            v-model="formCodigo.codigo"
            label="Código de verificación"
            placeholder="8 caracteres"
            required
            :error="formCodigo.errors.codigo"
            :hint="`El código vence en ${vigenciaMinutos} minutos.`"
        />

        <Button type="submit" block :loading="formCodigo.processing">
            Verificar
        </Button>

        <Button href="/recuperar" variant="ghost" size="sm" block>
            Solicitar otro código
        </Button>
    </form>

    <!-- ---------- Paso 3 ---------- -->
    <form
        v-else
        class="space-y-5"
        @submit.prevent="formPassword.post('/recuperar/restablecer')"
    >
        <div>
            <h1 class="font-display text-xl text-fg">Nueva contraseña</h1>
            <p class="mt-1 text-sm text-fg-muted">Elige una contraseña que no hayas usado antes.</p>
        </div>

        <Input
            id="password"
            v-model="formPassword.password"
            label="Nueva contraseña"
            type="password"
            required
            :error="formPassword.errors.password"
        />

        <Input
            id="password_confirmation"
            v-model="formPassword.password_confirmation"
            label="Confirma la contraseña"
            type="password"
            required
        />

        <Alert tone="info" title="Tu contraseña debe cumplir">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <li v-for="r in requisitos" :key="r">{{ r }}</li>
            </ul>
        </Alert>

        <Button type="submit" block :loading="formPassword.processing">
            Guardar contraseña
        </Button>
    </form>
</template>
