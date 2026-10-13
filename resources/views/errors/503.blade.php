<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ config('app.name') }} — 503</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-500 text-gray-800">
    <div class="w-full max-w-md card p-8 text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/30 text-white flex items-center justify-center">
            <x-icon name="clock" class="w-8 h-8" />
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900 mb-1">503</h1>
        <p class="text-lg text-gray-600 mb-6">{{ __('messages.service_unavailable') }}</p>
        <a href="{{ url('/') }}" class="btn-primary inline-flex">
            <x-icon name="home" class="w-5 h-5" /> {{ __('messages.back_home') }}
        </a>
    </div>
</body>
</html>
