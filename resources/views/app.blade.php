<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="dark light">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!--
            Tema dipasang SEBELUM cat pertama.
            Kalau ini dijalankan dari Vue (setelah bundle dimuat), user yang
            memilih mode terang akan melihat kedipan gelap dulu — persis
            keluhan "desainnya nggak sesuai" yang membuat pass ini ada.
        -->
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('guardian-theme');
                    var mode = stored === 'light' || stored === 'dark'
                        ? stored
                        : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');

                    document.documentElement.dataset.theme = mode;
                    document.documentElement.style.colorScheme = mode;
                } catch (e) {
                    document.documentElement.dataset.theme = 'dark';
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
