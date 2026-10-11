<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ config('app.name') }} — {{ __('messages.register') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-500 text-gray-800">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-brand-gradient shadow-lg shadow-emerald-600/30 flex items-center justify-center">
                <img src="{{ asset('icon.svg') }}" alt="" class="w-10 h-10">
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ __('messages.create_account') }}</h1>
        </div>

        <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2.5 mb-4 flex items-start gap-1.5">
            <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.register_allow_hint') }}
        </p>

        @if($errors->any())
            <div class="alert-error mb-4">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0" /> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label class="label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="input">
            </div>

            <div>
                <label class="label">{{ __('messages.mobile_number') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="{{ __('messages.phone_placeholder') }}" class="input">
            </div>

            <div>
                <label class="label">{{ __('messages.email') }} {{ __('messages.optional') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input">
            </div>

            <div>
                <label class="label">{{ __('messages.password') }}</label>
                <input type="password" name="password" required class="input">
            </div>

            <div>
                <label class="label">{{ __('messages.confirm_password') }}</label>
                <input type="password" name="password_confirmation" required class="input">
            </div>

            <button type="submit" class="btn-primary w-full">
                <x-icon name="plus-circle" class="w-5 h-5" /> {{ __('messages.register') }}
            </button>
        </form>

        <p class="text-center text-sm mt-5 text-gray-600">
            <a href="{{ route('login') }}" class="text-emerald-700 font-semibold inline-flex items-center gap-1.5">
                <x-icon name="arrow-right-on-rectangle" class="w-4 h-4" /> {{ __('messages.already_have_account') }}
            </a>
        </p>
    </div>

</body>
</html>
