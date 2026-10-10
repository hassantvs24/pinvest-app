<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — 404</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 text-center">
        <div class="text-6xl mb-4">🔍</div>
        <p class="text-lg text-gray-700 mb-6">{{ __('messages.page_not_found') }}</p>
        <a href="{{ url('/') }}" class="inline-block bg-emerald-700 text-white font-bold rounded-lg px-6 py-3">
            🏠 {{ __('messages.back_home') }}
        </a>
    </div>
</body>
</html>
