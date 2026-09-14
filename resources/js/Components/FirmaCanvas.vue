<script setup lang="ts">
/**
 * Firma autógrafa digitalizada (manual §3.1.1).
 *
 * El cliente traza su firma con el mouse o con el dedo. Se usan Pointer
 * Events —no mouse + touch por separado— porque cubren mouse, dedo y lápiz
 * con el mismo código y evitan el doble disparo clásico en dispositivos
 * táctiles.
 *
 * El canvas se dibuja a la resolución real de la pantalla
 * (`devicePixelRatio`) para que la firma no salga pixelada en móviles, que es
 * justo donde más gente va a firmar con el dedo.
 */
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';

const emit = defineEmits<{ cambio: [firma: string | null] }>();

const canvas = ref<HTMLCanvasElement | null>(null);
const ctx = shallowRef<CanvasRenderingContext2D | null>(null);
const dibujando = ref(false);
const tieneTrazo = ref(false);

function preparar(): void {
    const el = canvas.value;
    if (!el) return;

    const ratio = window.devicePixelRatio || 1;
    const caja = el.getBoundingClientRect();

    el.width = caja.width * ratio;
    el.height = caja.height * ratio;

    const c = el.getContext('2d');
    if (!c) return;

    c.scale(ratio, ratio);
    c.lineWidth = 2;
    c.lineCap = 'round';
    c.lineJoin = 'round';
    // Color fijo, no token de tema: la firma se guarda como imagen y acaba
    // dentro de un PDF de fondo blanco. En tema oscuro, una firma clara
    // quedaría invisible en el documento.
    c.strokeStyle = '#18233e';

    ctx.value = c;
}

function posicion(e: PointerEvent): [number, number] {
    const caja = canvas.value!.getBoundingClientRect();
    return [e.clientX - caja.left, e.clientY - caja.top];
}

function iniciar(e: PointerEvent): void {
    if (!ctx.value) return;

    dibujando.value = true;
    // Captura el puntero para que el trazo no se corte si el dedo se sale
    // del canvas a media firma.
    canvas.value?.setPointerCapture(e.pointerId);

    const [x, y] = posicion(e);
    ctx.value.beginPath();
    ctx.value.moveTo(x, y);
}

function trazar(e: PointerEvent): void {
    if (!dibujando.value || !ctx.value) return;

    const [x, y] = posicion(e);
    ctx.value.lineTo(x, y);
    ctx.value.stroke();

    if (!tieneTrazo.value) {
        tieneTrazo.value = true;
    }
}

function terminar(e: PointerEvent): void {
    if (!dibujando.value) return;

    dibujando.value = false;
    canvas.value?.releasePointerCapture(e.pointerId);

    emitirFirma();
}

function emitirFirma(): void {
    if (!tieneTrazo.value || !canvas.value) {
        emit('cambio', null);
        return;
    }

    emit('cambio', canvas.value.toDataURL('image/png'));
}

function limpiar(): void {
    const el = canvas.value;
    if (!el || !ctx.value) return;

    ctx.value.clearRect(0, 0, el.width, el.height);
    tieneTrazo.value = false;
    emit('cambio', null);
}

defineExpose({ limpiar });

onMounted(() => {
    preparar();
    window.addEventListener('resize', preparar);
});

onUnmounted(() => window.removeEventListener('resize', preparar));
</script>

<template>
    <div>
        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-line-strong bg-white">
            <canvas
                ref="canvas"
                class="block h-44 w-full cursor-crosshair touch-none"
                @pointerdown="iniciar"
                @pointermove="trazar"
                @pointerup="terminar"
                @pointercancel="terminar"
                @pointerleave="terminar"
            />

            <!-- Linea guia, como en un papel. Se apaga al empezar a trazar. -->
            <div
                v-if="!tieneTrazo"
                class="pointer-events-none absolute inset-x-8 bottom-10 border-b border-navy-200"
            />
            <p
                v-if="!tieneTrazo"
                class="pointer-events-none absolute inset-x-0 bottom-4 text-center text-xs text-navy-400"
            >
                Firma aquí con el mouse o con el dedo
            </p>
        </div>

        <div class="mt-2 flex items-center justify-between">
            <p class="text-xs text-fg-subtle">
                Tu firma quedará asentada en el contrato.
            </p>
            <button
                type="button"
                class="text-xs font-medium text-accent disabled:opacity-40"
                :disabled="!tieneTrazo"
                @click="limpiar"
            >
                Borrar y repetir
            </button>
        </div>
    </div>
</template>
