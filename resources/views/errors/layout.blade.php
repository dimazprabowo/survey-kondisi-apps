<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ app_name() }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/bki-main.webp') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/bki-main.webp') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        // Initialize dark mode from localStorage before Alpine loads
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        /* Floating Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(1deg); }
            50% { transform: translateY(-20px) rotate(0deg); }
            75% { transform: translateY(-10px) rotate(-1deg); }
        }
        
        /* Pulse Glow Animation */
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.3); }
            50% { box-shadow: 0 0 40px rgba(59, 130, 246, 0.6); }
        }
        
        /* Shake Animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Bounce In Animation */
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { opacity: 1; transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
        
        /* Slide Up Animation */
        @keyframes slideUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        /* Rotate Animation */
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Wave Animation */
        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(20deg); }
            75% { transform: rotate(-15deg); }
        }
        
        /* Gradient Shift */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Particle Float */
        @keyframes particleFloat {
            0%, 100% { transform: translateY(0) translateX(0) scale(1); opacity: 0.6; }
            25% { transform: translateY(-20px) translateX(10px) scale(1.1); opacity: 0.8; }
            50% { transform: translateY(-40px) translateX(-5px) scale(0.9); opacity: 0.4; }
            75% { transform: translateY(-20px) translateX(-10px) scale(1.05); opacity: 0.7; }
        }
        
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-pulse-glow { animation: pulseGlow 2s ease-in-out infinite; }
        .animate-shake { animation: shake 0.5s ease-in-out; }
        .animate-bounce-in { animation: bounceIn 0.8s ease-out forwards; }
        .animate-slide-up { animation: slideUp 0.6s ease-out forwards; }
        .animate-rotate { animation: rotate 20s linear infinite; }
        .animate-wave { animation: wave 1s ease-in-out infinite; }
        .animate-gradient { 
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite; 
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        
        /* Particle styles */
        .particle {
            position: absolute;
            border-radius: 50%;
            animation: particleFloat 8s ease-in-out infinite;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Background Particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            @yield('particles')
        </div>
        
        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-lg">
            @yield('content')
        </div>
    </div>
</body>
</html>
