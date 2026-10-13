@extends('layouts.app')

@section('title', __('messages.profile'))

@section('content')
    <div class="max-w-xl mx-auto space-y-4">
        <div class="card p-5">
            <div class="text-center mb-4">
                <x-avatar :user="$user" size="72" class="mb-2" />
                <div class="text-xl font-bold">{{ $user->name }}</div>
                <span class="badge mt-1 {{ $user->isOwner() ? 'badge-pending' : 'badge-confirmed' }}">
                    {{ $user->isOwner() ? __('messages.role_owner') : __('messages.role_partner') }}
                </span>
            </div>

            <ul class="divide-y divide-gray-100 text-sm">
                <li class="py-3 flex justify-between"><span class="text-gray-500 flex items-center gap-1.5"><x-icon name="phone" class="w-4 h-4" /> {{ __('messages.mobile_number') }}</span> <span class="font-medium">{{ $user->phone }}</span></li>
                <li class="py-3 flex justify-between"><span class="text-gray-500 flex items-center gap-1.5"><x-icon name="envelope" class="w-4 h-4" /> {{ __('messages.email') }}</span> <span class="font-medium">{{ $user->email ?? '—' }}</span></li>
                @if(! $user->isOwner())
                    <li class="py-3 flex justify-between"><span class="text-gray-500 flex items-center gap-1.5"><x-icon name="receipt-percent" class="w-4 h-4" /> {{ __('messages.commission_rate') }}</span> <span class="font-medium">{{ $user->commission_rate }}%</span></li>
                @endif
                <li class="py-3 flex justify-between">
                    <span class="text-gray-500 flex items-center gap-1.5"><x-icon name="globe-alt" class="w-4 h-4" /> {{ __('messages.language') }}</span>
                    <span class="font-medium">{{ $user->preferred_language === 'en' ? __('messages.language_en') : __('messages.language_bn') }}</span>
                </li>
            </ul>
        </div>

        @if(! $user->isOwner())
            <a href="{{ route('commissions.index') }}"
               class="card card-hover flex items-center justify-between p-4 font-bold text-emerald-700">
                <span class="flex items-center gap-2">
                    <x-icon name="receipt-percent" class="w-5 h-5" /> {{ __('messages.my_commission') }}
                </span>
                <span class="text-xs font-normal text-gray-500">{{ __('messages.commission_due') }}: {{ \App\Support\Money::format($commissionDue) }} <x-icon name="arrow-right" class="w-4 h-4" /></span>
            </a>
        @endif

        <div class="card p-5">
            <h2 class="section-title"><x-icon name="lock-closed" class="w-5 h-5 text-emerald-600" /> {{ __('messages.change_password') }}</h2>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="label">{{ __('messages.current_password') }}</label>
                    <input type="password" name="current_password" required class="input-sm">
                </div>
                <div>
                    <label class="label">{{ __('messages.new_password') }}</label>
                    <input type="password" name="password" required class="input-sm">
                </div>
                <div>
                    <label class="label">{{ __('messages.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" required class="input-sm">
                </div>
                <button type="submit" class="btn-primary w-full">
                    {{ __('messages.change_password') }}
                </button>
            </form>
        </div>

        <a href="{{ route('language.show') }}"
           class="card card-hover p-4 text-center font-bold text-emerald-700 flex items-center justify-center gap-2">
            <x-icon name="globe-alt" class="w-5 h-5" /> {{ __('messages.change_language') }}
        </a>
    </div>
@endsection
