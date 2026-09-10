<script setup lang="ts">
/**
 * Alta de cliente inversionista (manual §3.1.1).
 *
 * Los requisitos de contraseña se muestran ANTES de que el usuario falle:
 * la política del §4.1 es estricta y descubrirla a base de errores es una
 * forma segura de perder registros.
 */
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Select from '@/Components/ui/Select.vue';
import Alert from '@/Components/ui/Alert.vue';

defineOptions({ layout: AuthLayout });

const form = useForm({
    email: '',
    nombre: '',
    apellidoPaterno: '',
    apellidoMaterno: '',
    tipoPersona: 1 as 1 | 2,
    password: '',
    password_confirmation: '',
    terminos: false,
});

const tiposPersona = [
    { value: 1, label: 'Persona física' },
    { value: 2, label: 'Persona moral' },
];

const requisitos = [
    'Entre 8 y 30 caracteres',
    'Al menos una mayúscula y una minúscula',
    'Al menos un dígito y un carácter especial',
    'Sin tu correo ni el nombre de la institución',
    'Sin más de 3 caracteres iguales o en secuencia seguidos',
];
</script>

<template>
    <Head title="Crear cuenta" />

    <form class="space-y-5" @submit.prevent="form.post('/registro')">
        <div>
            <h1 class="font-display text-xl text-fg">Crear cuenta</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Empieza a invertir en proyectos inmobiliarios.
            </p>
        </div>

        <div>
            <label for="tipoPersona" class="mb-1.5 block text-sm font-medium text-fg">
                Tipo de persona
            </label>
            <Select
                id="tipoPersona"
                v-model="form.tipoPersona"
                :options="tiposPersona"
                :error="form.errors.tipoPersona"
            />
        </div>

        <Input
            id="nombre"
            v-model="form.nombre"
            :label="form.tipoPersona === 2 ? 'Razón social' : 'Nombre(s)'"
            required
            :error="form.errors.nombre"
        />

        <div v-if="form.tipoPersona === 1" class="grid gap-4 sm:grid-cols-2">
            <Input
                id="apellidoPaterno"
                v-model="form.apellidoPaterno"
                label="Apellido paterno"
                :error="form.errors.apellidoPaterno"
            />
            <Input
                id="apellidoMaterno"
                v-model="form.apellidoMaterno"
                label="Apellido materno"
                :error="form.errors.apellidoMaterno"
            />
        </div>

        <Input
            id="email"
            v-model="form.email"
            label="Correo electrónico"
            type="email"
            placeholder="tu@correo.com"
            required
            hint="Será tu identificador para iniciar sesión."
            :error="form.errors.email"
        />

        <Input
            id="password"
            v-model="form.password"
            label="Contraseña"
            type="password"
            required
            :error="form.errors.password"
        />

        <Input
            id="password_confirmation"
            v-model="form.password_confirmation"
            label="Confirma la contraseña"
            type="password"
            required
        />

        <Alert tone="info" title="Tu contraseña debe cumplir">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <li v-for="r in requisitos" :key="r">{{ r }}</li>
            </ul>
        </Alert>

        <Checkbox id="terminos" v-model="form.terminos" :error="form.errors.terminos">
            Acepto los
            <a
                href="https://topkapital.com/terminos-y-condiciones/"
                target="_blank"
                rel="noopener"
                class="font-medium text-accent underline"
            >términos y condiciones</a>
            y el
            <a
                href="https://topkapital.com/aviso-de-privacidad/"
                target="_blank"
                rel="noopener"
                class="font-medium text-accent underline"
            >aviso de privacidad</a>.
        </Checkbox>

        <Button type="submit" block :loading="form.processing">Crear cuenta</Button>

        <Button href="/login" variant="ghost" size="sm" block>
            Ya tengo cuenta
        </Button>
    </form>
</template>
