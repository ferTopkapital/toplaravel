<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parámetros regulados
    |--------------------------------------------------------------------------
    |
    | Estos valores NO son preferencias de producto: salen del Manual de Uso de
    | Medios Electrónicos declarado ante la CNBV. Los valores por defecto de
    | este archivo son los que cumplen la norma, de modo que un entorno que no
    | declare nada queda conforme. Sólo el .env local los relaja.
    |
    | Ver docs/PLAN-MIGRACION.md, sección 8.
    |
    */

    'sesion' => [

        /*
         * Minutos de inactividad antes de cerrar la sesión (manual §4.4.4).
         *
         * El valor normativo son 5 minutos. En local se sube vía
         * SESSION_IDLE_MINUTES para que la sesión no expire cada rato
         * mientras se desarrolla — igual que hace TIMELOGOUT=30 en el
         * entorno Yii2. Nunca subirlo en producción.
         */
        'minutos_inactividad' => (int) env('SESSION_IDLE_MINUTES', 5),

        /* Segundos de aviso previo, con cuenta regresiva, antes de cerrar. */
        'segundos_aviso' => (int) env('SESSION_WARNING_SECONDS', 60),

        /*
         * Sesión única por identificador de cliente (manual §4.4.4).
         * No es configurable por producto; la bandera existe sólo para poder
         * apagarla en pruebas automatizadas.
         */
        'sesion_unica' => (bool) env('SESSION_SINGLE', true),
    ],

    'otp' => [
        /* Segundo factor: 8 caracteres, vigencia de 2 minutos (manual §4.4). */
        'longitud' => 8,
        'vigencia_segundos' => 120,
    ],

    'password' => [
        /* Política de contraseñas del manual §4.1. */
        'min' => 8,
        'max' => 30,

        /* Máximo de caracteres idénticos consecutivos permitidos ("aaa" sí, "aaaa" no). */
        'max_repetidos' => 3,

        /* Máximo de caracteres en secuencia permitidos ("abc" sí, "abcd" no). */
        'max_secuenciales' => 3,

        /* Palabras que no pueden aparecer en la contraseña. */
        'prohibidas' => ['topkapital', 'top kapital'],

        /*
         * Cuántas contraseñas anteriores se comparan para impedir reúso.
         *
         * `null` = todo el historial.
         *
         * Se deja en `null` para NO relajar el comportamiento actual. En la
         * app Yii2 esto se lee de `Yii::$app->params['maxPasswordHistory']`,
         * que **no está definido en ningún archivo de params**: la expresión
         * evalúa a null, `limit(null)` significa "sin límite" y termina
         * comparando contra todo el historial. Es decir, el comportamiento
         * vigente es "nunca reutilizar ninguna contraseña previa" — por
         * accidente, y emitiendo un warning de PHP en cada reset.
         *
         * Poner aquí un número finito sería aflojar un control de seguridad,
         * así que si se decide hacerlo debe ser una decisión consciente y
         * documentada, no una corrección silenciosa del descuido.
         */
        'historial_comparado' => null,
    ],

    'bloqueo' => [
        /* Intentos fallidos antes de bloquear la cuenta (manual §4.3.1). */
        'intentos_maximos' => 10,

        /* Días de inactividad antes de desactivar la cuenta (manual §4.3.2). */
        'dias_inactividad' => 365,

        /* Días de anticipación del aviso de desactivación. */
        'dias_aviso_previo' => 30,
    ],

    'kyc' => [
        /*
         * Umbral mensual que obliga a escalar de KYC Simplificado (Nivel 1) a
         * KYC Completo (Nivel 2), en pesos (manual §3.1.1).
         */
        'umbral_nivel_2' => 5000.00,
    ],

];
