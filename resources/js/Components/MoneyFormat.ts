/**
 * Formato de moneda y fechas en es-MX.
 *
 * Centralizado a propósito: si cada vista arma su propio Intl.NumberFormat,
 * tarde o temprano una muestra "$1,000" y otra "$1,000.00" para el mismo dato,
 * y en una app financiera eso se lee como un error de saldo.
 */

const pesos = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Variante compacta para tarjetas: $1.2M, $450K. */
const pesosCompacto = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    notation: 'compact',
    maximumFractionDigits: 1,
});

const fechaCorta = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'America/Mexico_City',
});

export function dinero(valor: number | null | undefined): string {
    if (valor === null || valor === undefined || Number.isNaN(valor)) return '—';
    return pesos.format(valor);
}

export function dineroCorto(valor: number | null | undefined): string {
    if (valor === null || valor === undefined || Number.isNaN(valor)) return '—';
    return pesosCompacto.format(valor);
}

export function porcentaje(valor: number | null | undefined, decimales = 1): string {
    if (valor === null || valor === undefined || Number.isNaN(valor)) return '—';
    return `${valor.toFixed(decimales)}%`;
}

export function fecha(iso: string | null | undefined): string {
    if (!iso) return '—';
    return fechaCorta.format(new Date(iso));
}
