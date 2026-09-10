<x-mail::message>
# Hola{{ $nombre ? ', ' . $nombre : '' }}

{{ $motivo }}

<x-mail::panel>
# {{ $codigo }}
</x-mail::panel>

Este código vence en **{{ $vigenciaMinutos }} minutos** y sólo puede usarse una vez.

Si no fuiste tú quien lo solicitó, **no lo compartas con nadie** y comunícate de inmediato
con nosotros a soporte@topkapital.com.

Top Kapital nunca te pedirá tu contraseña ni tus códigos de verificación por teléfono,
correo o mensaje.

Gracias,<br>
Top Kapital
</x-mail::message>
