<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ config('app.name') }} — {{ __('messages.login') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-500 text-gray-800">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-brand-gradient shadow-lg shadow-emerald-600/30 flex items-center justify-center">
                <img src="{{ asset('icon.svg') }}" alt="" class="w-10 h-10">
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('messages.login') }}</p>
        </div>

        @if($errors->any())
            <div class="alert-error mb-4">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0" /> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="label">{{ __('messages.email_or_phone') }}</label>
                <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus class="input">
            </div>

            <div>
                <label class="label">{{ __('messages.password') }}</label>
                <input type="password" name="password" required class="input">
            </div>

            <div class="flex items-center gap-2.5">
                <input type="checkbox" name="remember" id="remember" value="1"
                       class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 accent-emerald-600" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" class="text-sm text-gray-700">{{ __('messages.remember_me') }}</label>
            </div>

            <button type="submit" class="btn-primary w-full">
                <x-icon name="arrow-right-on-rectangle" class="w-5 h-5" /> {{ __('messages.login') }}
            </button>
        </form>

        <p class="text-center text-sm mt-5 text-gray-600">
            <a href="{{ route('register') }}" class="text-emerald-700 font-semibold inline-flex items-center gap-1.5">
                <x-icon name="plus-circle" class="w-4 h-4" /> {{ __('messages.no_account') }}
            </a>
        </p>
    </div>

    {{-- Android APK (Capacitor) native feel: no-op in normal browsers --}}
    <script src="{{ asset('js/capacitor-native.js') }}"></script>
</body>
</html>
