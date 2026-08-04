<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arr Wallet — P2P Trading Platform')</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js for lightweight client interactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    @yield('styles')
</head>
<body>
    <!-- Ambient Background Orbs -->
    <div class="bg-orb-1"></div>
    <div class="bg-orb-2"></div>

    @yield('content')

    @yield('scripts')
</body>
</html>
