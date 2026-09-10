<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Top Kapital') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">

    {{--
        Tema antes del primer pintado.
        Este script tiene que ser inline y estar ANTES de los estilos: si se
        aplicara la clase .dark desde Vue (despues de hidratar), el usuario en
        modo oscuro veria un destello blanco en cada carga.
    --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('topkapital.theme') || 'system';
                var dark = stored === 'dark' || (stored === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {
                // Sin localStorage se queda en claro; no vale la pena romper la carga.
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Antic+Slab&family=Nunito+Sans:opsz,wght@6..12,300..900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
