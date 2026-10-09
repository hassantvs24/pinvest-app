@extends('layouts.app')

@section('title', __('messages.profile'))

@section('content')
    <div class="max-w-xl mx-auto space-y-4">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <div class="text-center mb-4">
                <div class="text-5xl mb-2">👤</div>
                <div class="text-xl font-bold">{{ $user->name }}</div>
                <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full {{ $user->isOwner() ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                    {{ $user->isOwner() ? __('messages.role_owner') : __('messages.role_partner') }}
                </span>
            </div>

            <ul class="divide-y divide-gray-100 text-sm">
                <li class="py-3 flex justify-between"><span class="text-gray-500">📱 {{ __('messages.mobile_number') }}</span> <span class="font-medium">{{ $user->phone }}</span></li>
                <li class="py-3 flex justify-between"><span class="text-gray-500">✉️ {{ __('messages.email') }}</span> <span class="font-medium">{{ $user->email ?? '—' }}</span></li>
                @if(! $user->isOwner())
                    <li class="py-3 flex justify-between"><span class="text-gray-500">🤝 {{ __('messages.commission_rate') }}</span> <span class="font-medium">{{ $user->commission_rate }}%</span></li>
                @endif
                <li class="py-3 flex justify-between">
                    <span class="text-gray-500">🌐 {{ __('messages.language') }}</span>
                    <span class="font-medium">{{ $user->preferred_language === 'en' ? '🇬🇧 English' : '🇧🇩 বাংলা' }}</span>
                </li>
            </ul>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h2 class="font-bold text-lg mb-4">🔒 {{ __('messages.change_password') }}</h2>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('messages.current_password') }}</label>
                    <input type="password" name="current_password" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('messages.new_password') }}</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('messages.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg">
                    {{ __('messages.change_password') }}
                </button>
            </form>
        </div>

        <a href="{{ route('language.show') }}"
           class="block w-full bg-white rounded-2xl shadow-lg p-4 text-center font-bold text-emerald-700">
            🌐 {{ __('messages.change_language') }}
        </a>
    </div>
@endsection
