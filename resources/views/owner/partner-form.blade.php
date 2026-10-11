@extends('layouts.app')

@section('title', $partner ? __('messages.edit') : __('messages.new_partner'))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('owner.partners.index') }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h1 class="text-xl font-bold mb-4">👤 {{ $partner ? __('messages.edit') : __('messages.new_partner') }}</h1>

            <form method="POST"
                  action="{{ $partner ? route('owner.partners.update', ['partner' => $partner->id]) : route('owner.partners.store') }}"
                  class="space-y-4">
                @csrf
                @if($partner)
                    @method('PUT')
                @endif

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $partner->name ?? '') }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.mobile_number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $partner->phone ?? '') }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.email') }} {{ __('messages.optional') }}</label>
                    <input type="email" name="email" value="{{ old('email', $partner->email ?? '') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.password') }}{{ $partner ? ' ('.__('messages.optional').')' : '' }}</label>
                    <input type="password" name="password" {{ $partner ? '' : 'required' }}
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.commission_rate') }}</label>
                    <input type="number" name="commission_rate" step="0.01" min="0" max="100"
                           value="{{ old('commission_rate', $partner->commission_rate ?? 0) }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-gray-500 mt-1">💡 {{ __('messages.partner_rate_hint') }}</p>
                </div>

                <label class="flex items-center gap-3 bg-gray-50 rounded-lg px-4 py-3">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $partner->is_active ?? true) ? 'checked' : '' }}
                           class="w-6 h-6 accent-emerald-700">
                    <span class="font-medium">{{ __('messages.active') }}</span>
                </label>

                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-xl py-3">
                    💾 {{ __('messages.save') }}
                </button>
            </form>
        </div>
    </div>
@endsection
