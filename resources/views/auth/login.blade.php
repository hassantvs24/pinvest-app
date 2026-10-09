<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — {{ __('messages.login') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: system-ui, -apple-system, sans-serif; }</style>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-6">
        <div class="text-center mb-6">
            <div class="text-5xl mb-2">📦</div>
            <h1 class="text-2xl font-bold text-emerald-700">{{ config('app.name') }}</h1>
        </div>

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3 mb-4">❌ {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.email_or_phone') }}</label>
                <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.password') }}</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-lg py-3">
                🚪 {{ __('messages.login') }}
            </button>
        </form>

        <p class="text-center text-sm mt-4 text-gray-600">
            <a href="{{ route('register') }}" class="text-emerald-700 font-medium">➕ {{ __('messages.no_account') }}</a>
        </p>
    </div>

</body>
</html>
