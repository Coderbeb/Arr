<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arr Wallet — P2P Trading Platform')</title>
    
    <!-- Fonts loaded via globals.css — preconnect only -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Tailwind CSS (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Init theme
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-deep-900 text-gray-900 dark:text-gray-100 font-sans antialiased selection:bg-gold-500/30 min-h-screen relative overflow-x-hidden">
    
    <!-- Ambient Background -->
    <div class="fluid-morph absolute -top-40 -left-40 w-96 h-96 bg-gold-400/20 dark:bg-gold-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fluid-morph absolute -bottom-40 -right-40 w-96 h-96 bg-purple-500/20 dark:bg-purple-600/10 rounded-full blur-3xl pointer-events-none" style="animation-delay: -2s;"></div>

    <!-- Theme Toggle (Floating) -->
    <button onclick="toggleTheme()" class="fixed top-4 right-4 z-50 p-3 rounded-full bg-white/80 dark:bg-black/50 backdrop-blur-md shadow-lg border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:scale-110 transition-transform">
        <span class="dark:hidden">🌙</span>
        <span class="hidden dark:inline">☀️</span>
    </button>
    <script>
        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }
    </script>

    <div class="min-h-screen w-full flex flex-col justify-center py-10 sm:py-16">
        @yield('content')
    </div>

</body>
</html>
