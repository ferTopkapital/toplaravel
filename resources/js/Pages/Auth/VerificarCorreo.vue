<script setup lang="ts">
/** Verificación del correo con código de un solo uso (manual §3.1.1). */
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Alert from '@/Components/ui/Alert.vue';

defineOptions({ layout: AuthLayout });

defineProps<{ email: string }>();

const page = usePage();
const aviso = computed(() => (page.props.flash as any)?.success ?? null);

const form = useForm({ codigo: '' });
const reenvio = useForm({});
</script>

<template>
    <Head title="Verifica tu correo" />

    <div class="space-y-5">
        <div>
            <h1 class="font-display text-xl text-fg">Verifica tu correo</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Enviamos un código de 8 caracteres a
                <span class="font-medium text-fg">{{ email }}</span>.
            </p>
        </div>

        <Alert v-if="aviso" tone="success">{{ aviso }}</Alert>

        <form class="space-y-5" @submit.prevent="form.post('/registro/verificar')">
            <Input
                id="codigo"
                v-model="form.codigo"
                label="Código de verificación"
                placeholder="8 caracteres"
                required
                :error="form.errors.codigo"
                hint="El código vence en 2 minutos."
            />

            <Button type="submit" block :loading="form.processing">Verificar</Button>
        </form>

        <Button
            variant="ghost"
            size="sm"
            block
            :loading="reenvio.processing"
            @click="reenvio.post('/registro/reenviar')"
        >
            No me llegó, enviar otro código
        </Button>
    </div>
</template>
