<script setup lang="ts">
/**
 * Onboarding del inversionista (manual §3.1.1).
 *
 * El paso 3 (archivos) sólo aparece cuando aplica KYC Nivel 2: para Nivel 1
 * el manual dice que "no se presenta o permanece inactivo". Se omite del todo
 * en vez de mostrarlo deshabilitado, para no pedirle al cliente documentación
 * que la norma no le exige.
 */
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Alert from '@/Components/ui/Alert.vue';
import Badge from '@/Components/ui/Badge.vue';
import Stepper, { type Paso } from '@/Components/wizard/Stepper.vue';
import FirmaCanvas from '@/Components/FirmaCanvas.vue';
import { dinero } from '@/Components/MoneyFormat';

const props = defineProps<{
    usuario: Record<string, any>;
    kyc: { nivel: number; requiereDocumentacion: boolean; umbral: number; acumulado: number };
    avance: { informacion: boolean; archivos: boolean; constancia: boolean; contrato: boolean };
    estatus: number;
}>();

const page = usePage();
const aviso = computed(() => (page.props.flash as any)?.success ?? null);

const pasos = computed<Paso[]>(() => {
    const lista: Paso[] = [
        { id: 'info', label: 'Información', hint: 'Datos generales' },
        { id: 'benef', label: 'Beneficiarios', hint: 'Opcional' },
    ];

    if (props.kyc.requiereDocumentacion) {
        lista.push({ id: 'archivos', label: 'Archivos', hint: 'KYC nivel 2' });
    }

    lista.push(
        { id: 'constancia', label: 'Constancia', hint: 'Riesgos' },
        { id: 'contrato', label: 'Contrato', hint: 'Firma' },
    );

    return lista;
});

const actual = ref(0);

const formInfo = useForm({
    nombre: props.usuario.nombre ?? '',
    apellidoPaterno: props.usuario.apellidoPaterno ?? '',
    apellidoMaterno: props.usuario.apellidoMaterno ?? '',
    RFC: props.usuario.RFC ?? '',
    CURP: props.usuario.CURP ?? '',
    telefono: props.usuario.telefono ?? '',
    fechaNacimiento: props.usuario.fechaNacimiento ?? '',
    nacionalidad: props.usuario.nacionalidad ?? 'Mexicana',
    profesion: props.usuario.profesion ?? '',
    codigoPostal: props.usuario.codigoPostal ?? '',
    calle: props.usuario.calle ?? '',
    numeroExterior: props.usuario.numeroExterior ?? '',
    numeroInterior: props.usuario.numeroInterior ?? '',
    colonia: props.usuario.colonia ?? '',
    municipio_delegacion: props.usuario.municipio_delegacion ?? '',
    genero: props.usuario.genero ?? null,
    tipoInversionista: props.usuario.tipoInversionista ?? null,
    puestoGobierno: props.usuario.puestoGobierno ?? null,
});

const formConstancia = useForm({
    riesgoPerdida: false,
    riesgoLiquidez: false,
    riesgoInformacion: false,
    riesgoRendimiento: false,
    sinAprobacion: false,
    sinAsesoria: false,
});

const formContrato = useForm({ firma: '' as string });
const formEnvio = useForm({});

const esMoral = computed(() => props.usuario.tipoPersona === 2);

const generos = [
    { value: 1, label: 'Masculino' },
    { value: 2, label: 'Femenino' },
];

const tiposInversionista = [
    { value: 1, label: 'Principiante' },
    { value: 2, label: 'Experto' },
    { value: 3, label: 'Relacionado' },
];

const siNo = [
    { value: 0, label: 'No' },
    { value: 1, label: 'Sí' },
];

/** Los seis riesgos que la institución debe acreditar que reveló. */
const riesgos = [
    { campo: 'riesgoPerdida', texto: 'Entiendo que puedo perder parte o la totalidad de los recursos que invierta.' },
    { campo: 'riesgoLiquidez', texto: 'Entiendo que no hay garantía de poder retirar mi inversión antes del plazo pactado.' },
    { campo: 'riesgoInformacion', texto: 'Entiendo que la información de los proyectos la proporciona el solicitante y Top Kapital no garantiza su veracidad.' },
    { campo: 'riesgoRendimiento', texto: 'Entiendo que los rendimientos no están garantizados y dependen del desempeño del proyecto.' },
    { campo: 'sinAprobacion', texto: 'Entiendo que la CNBV no aprueba ni supervisa los proyectos publicados en la plataforma.' },
    { campo: 'sinAsesoria', texto: 'Entiendo que Top Kapital no me proporciona asesoría de inversión.' },
] as const;

const todosLosRiesgos = computed(() =>
    riesgos.every((r) => formConstancia[r.campo] === true),
);

function guardarInfo(): void {
    formInfo.post('/onboarding/informacion', {
        preserveScroll: true,
        onSuccess: () => (actual.value = 1),
    });
}

function firmarConstancia(): void {
    formConstancia.post('/onboarding/constancia', { preserveScroll: true });
}

function alFirmar(firma: string | null): void {
    formContrato.firma = firma ?? '';
}

function firmarContrato(): void {
    formContrato.post('/onboarding/contrato', { preserveScroll: true });
}
</script>

<template>
    <Head title="Completa tu perfil" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl text-fg">Completa tu perfil</h1>
            <p class="mt-1 text-sm text-fg-muted">
                Necesitamos estos datos para que puedas invertir.
            </p>
        </div>

        <Alert v-if="aviso" tone="success">{{ aviso }}</Alert>

        <!-- Nivel de KYC: se explica, no se esconde. -->
        <Card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-fg">
                        KYC {{ kyc.nivel === 2 ? 'Completo (nivel 2)' : 'Simplificado (nivel 1)' }}
                    </p>
                    <p class="mt-0.5 text-sm text-fg-muted">
                        <template v-if="kyc.requiereDocumentacion">
                            Tu perfil requiere identificación oficial y una foto sosteniéndola.
                        </template>
                        <template v-else>
                            Con este nivel puedes invertir hasta {{ dinero(kyc.umbral) }}.
                            Si lo superas, te pediremos tu identificación.
                        </template>
                    </p>
                </div>
                <Badge :tone="kyc.nivel === 2 ? 'accent' : 'neutral'">Nivel {{ kyc.nivel }}</Badge>
            </div>
        </Card>

        <Card>
            <Stepper :pasos="pasos" :actual="actual" :completado-hasta="pasos.length - 1" @ir="actual = $event" />
        </Card>

        <!-- ---------- Paso 1: información general ---------- -->
        <Card v-if="pasos[actual]?.id === 'info'" title="Información general">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="guardarInfo">
                <Input
                    id="nombre"
                    v-model="formInfo.nombre"
                    :label="esMoral ? 'Razón social' : 'Nombre(s)'"
                    required
                    :error="formInfo.errors.nombre"
                />

                <template v-if="!esMoral">
                    <Input id="apellidoPaterno" v-model="formInfo.apellidoPaterno" label="Apellido paterno" required :error="formInfo.errors.apellidoPaterno" />
                    <Input id="apellidoMaterno" v-model="formInfo.apellidoMaterno" label="Apellido materno" :error="formInfo.errors.apellidoMaterno" />
                    <Input id="CURP" v-model="formInfo.CURP" label="CURP" required hint="18 caracteres" :error="formInfo.errors.CURP" />
                    <Input id="fechaNacimiento" v-model="formInfo.fechaNacimiento" label="Fecha de nacimiento" type="date" required :error="formInfo.errors.fechaNacimiento" />
                </template>

                <Input id="RFC" v-model="formInfo.RFC" label="RFC" required :hint="esMoral ? '12 caracteres' : '13 caracteres'" :error="formInfo.errors.RFC" />
                <Input id="telefono" v-model="formInfo.telefono" label="Teléfono" type="tel" required :error="formInfo.errors.telefono" />

                <div v-if="!esMoral">
                    <label for="genero" class="mb-1.5 block text-sm font-medium text-fg">Género</label>
                    <Select id="genero" v-model="formInfo.genero" :options="generos" :error="formInfo.errors.genero" />
                </div>

                <Input id="nacionalidad" v-model="formInfo.nacionalidad" label="Nacionalidad" required :error="formInfo.errors.nacionalidad" />
                <Input id="profesion" v-model="formInfo.profesion" :label="esMoral ? 'Giro' : 'Ocupación'" required :error="formInfo.errors.profesion" />

                <div class="sm:col-span-2">
                    <h3 class="mb-3 mt-2 text-sm font-semibold text-fg">Domicilio</h3>
                </div>

                <Input id="calle" v-model="formInfo.calle" label="Calle" required :error="formInfo.errors.calle" />
                <div class="grid grid-cols-2 gap-3">
                    <Input id="numeroExterior" v-model="formInfo.numeroExterior" label="Núm. exterior" required :error="formInfo.errors.numeroExterior" />
                    <Input id="numeroInterior" v-model="formInfo.numeroInterior" label="Núm. interior" :error="formInfo.errors.numeroInterior" />
                </div>
                <Input id="colonia" v-model="formInfo.colonia" label="Colonia" required :error="formInfo.errors.colonia" />
                <Input id="municipio_delegacion" v-model="formInfo.municipio_delegacion" label="Municipio o delegación" required :error="formInfo.errors.municipio_delegacion" />
                <Input id="codigoPostal" v-model="formInfo.codigoPostal" label="Código postal" required :error="formInfo.errors.codigoPostal" />

                <div class="sm:col-span-2">
                    <h3 class="mb-3 mt-2 text-sm font-semibold text-fg">Perfil de inversión</h3>
                </div>

                <div>
                    <label for="tipoInversionista" class="mb-1.5 block text-sm font-medium text-fg">
                        Tipo de inversionista
                    </label>
                    <Select id="tipoInversionista" v-model="formInfo.tipoInversionista" :options="tiposInversionista" :error="formInfo.errors.tipoInversionista" />
                </div>

                <div>
                    <label for="puestoGobierno" class="mb-1.5 block text-sm font-medium text-fg">
                        ¿Desempeñas o desempeñaste un puesto público?
                    </label>
                    <Select id="puestoGobierno" v-model="formInfo.puestoGobierno" :options="siNo" :error="formInfo.errors.puestoGobierno" />
                </div>

                <div class="sm:col-span-2">
                    <Button type="submit" :loading="formInfo.processing">Guardar y continuar</Button>
                </div>
            </form>
        </Card>

        <!-- ---------- Paso 2: beneficiarios ---------- -->
        <Card v-else-if="pasos[actual]?.id === 'benef'" title="Beneficiarios">
            <Alert tone="info">
                Registrar beneficiarios <strong>no es obligatorio</strong> para terminar tu
                perfil: puedes hacerlo cuando quieras desde tu perfil.
            </Alert>

            <template #footer>
                <div class="flex justify-between">
                    <Button variant="secondary" size="sm" @click="actual--">Anterior</Button>
                    <Button size="sm" @click="actual++">Continuar</Button>
                </div>
            </template>
        </Card>

        <!-- ---------- Paso 3: archivos (sólo KYC 2) ---------- -->
        <Card v-else-if="pasos[actual]?.id === 'archivos'" title="Documentación de identidad">
            <Alert tone="warn" title="En construcción">
                La carga de identificación oficial y la verificación biométrica llegan en la
                siguiente entrega. Mientras tanto puedes avanzar al resto del wizard.
            </Alert>

            <template #footer>
                <div class="flex justify-between">
                    <Button variant="secondary" size="sm" @click="actual--">Anterior</Button>
                    <Button size="sm" @click="actual++">Continuar</Button>
                </div>
            </template>
        </Card>

        <!-- ---------- Paso 4: constancia de riesgos ---------- -->
        <Card
            v-else-if="pasos[actual]?.id === 'constancia'"
            title="Constancia de conocimiento de riesgos"
            subtitle="Lee cada punto y reconócelo. Es un requisito para poder invertir."
        >
            <Alert v-if="avance.constancia" tone="success" title="Ya la firmaste">
                Tu constancia de conocimiento de riesgos quedó registrada.
            </Alert>

            <form v-else class="space-y-3" @submit.prevent="firmarConstancia">
                <Checkbox
                    v-for="r in riesgos"
                    :key="r.campo"
                    :id="r.campo"
                    v-model="formConstancia[r.campo]"
                    :error="formConstancia.errors[r.campo]"
                >
                    {{ r.texto }}
                </Checkbox>

                <Button type="submit" :disabled="!todosLosRiesgos" :loading="formConstancia.processing">
                    Firmar constancia
                </Button>
            </form>

            <template #footer>
                <div class="flex justify-between">
                    <Button variant="secondary" size="sm" @click="actual--">Anterior</Button>
                    <Button size="sm" :disabled="!avance.constancia" @click="actual++">Continuar</Button>
                </div>
            </template>
        </Card>

        <!-- ---------- Paso 5: contrato ---------- -->
        <Card
            v-else-if="pasos[actual]?.id === 'contrato'"
            title="Contrato General de Comisión Mercantil"
            subtitle="Se firma una sola vez y regula todas tus inversiones. No tendrás que firmarlo de nuevo en cada proyecto."
        >
            <Alert v-if="avance.contrato" tone="success" title="Contrato firmado">
                Tu contrato quedó firmado y registrado.
            </Alert>

            <div v-else class="space-y-4">
                <Alert v-if="!avance.constancia" tone="warn">
                    Primero firma la constancia de conocimiento de riesgos.
                </Alert>

                <template v-else>
                    <FirmaCanvas @cambio="alFirmar" />

                    <p v-if="formContrato.errors.firma" class="text-xs text-danger-500">
                        {{ formContrato.errors.firma }}
                    </p>

                    <Button
                        :disabled="!formContrato.firma"
                        :loading="formContrato.processing"
                        @click="firmarContrato"
                    >
                        Firmar contrato
                    </Button>
                </template>
            </div>

            <template #footer>
                <div class="space-y-3">
                    <p v-if="formEnvio.errors.wizard" class="text-sm text-danger-500">
                        {{ formEnvio.errors.wizard }}
                    </p>

                    <div class="flex justify-between">
                        <Button variant="secondary" size="sm" @click="actual--">Anterior</Button>
                        <Button
                            size="sm"
                            :loading="formEnvio.processing"
                            @click="formEnvio.post('/onboarding/enviar', { preserveScroll: true })"
                        >
                            Enviar a revisión
                        </Button>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>
