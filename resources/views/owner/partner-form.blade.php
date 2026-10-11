@extends('layouts.app')

@section('title', $partner ? __('messages.edit') : __('messages.new_partner'))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('owner.partners.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 mb-3">
            <x-icon name="arrow-left" class="w-4 h-4" /> {{ __('messages.back') }}
        </a>

        <div class="card p-5">
            <h1 class="text-xl font-bold mb-4 flex items-center gap-2">
                <x-icon name="user-circle" class="w-6 h-6 text-emerald-600" /> {{ $partner ? __('messages.edit') : __('messages.new_partner') }}
            </h1>

            <form method="POST"
                  action="{{ $partner ? route('owner.partners.update', ['partner' => $partner->id]) : route('owner.partners.store') }}"
                  class="space-y-4">
                @csrf
                @if($partner)
                    @method('PUT')
                @endif

                <div>
                    <label class="label">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $partner->name ?? '') }}" required class="input">
                </div>

                <div>
                    <label class="label">{{ __('messages.mobile_number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $partner->phone ?? '') }}" required class="input">
                </div>

                <div>
                    <label class="label">{{ __('messages.email') }} {{ __('messages.optional') }}</label>
                    <input type="email" name="email" value="{{ old('email', $partner->email ?? '') }}" class="input">
                </div>

                <div>
                    <label class="label">{{ __('messages.password') }}{{ $partner ? ' ('.__('messages.optional').')' : '' }}</label>
                    <input type="password" name="password" {{ $partner ? '' : 'required' }} class="input">
                </div>

                <div>
                    <label class="label">{{ __('messages.commission_rate') }}</label>
                    <input type="number" name="commission_rate" step="0.01" min="0" max="100"
                           value="{{ old('commission_rate', $partner->commission_rate ?? 0) }}" required class="input">
                    <p class="text-xs text-gray-500 mt-1 flex items-start gap-1.5">
                        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.partner_rate_hint') }}
                    </p>
                </div>

                <label class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $partner->is_active ?? true) ? 'checked' : '' }}
                           class="w-6 h-6 accent-emerald-600">
                    <span class="font-medium">{{ __('messages.active') }}</span>
                </label>

                <button type="submit" class="btn-primary w-full text-lg">
                    <x-icon name="check" class="w-5 h-5" /> {{ __('messages.save') }}
                </button>
            </form>
        </div>
    </div>
@endsection
