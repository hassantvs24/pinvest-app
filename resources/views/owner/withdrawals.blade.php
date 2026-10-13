@extends('layouts.app')

@section('title', __('messages.withdraw_profit'))

@section('content')
    <div class="card mb-4 border-l-4 border-l-amber-500">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="building-library" class="w-4 h-4" /> {{ __('messages.total_withdrawn') }}
        </div>
        <div class="text-2xl font-bold">{{ \App\Support\Money::format($total) }}</div>
    </div>

    <div class="card mb-4 border-l-4 border-l-emerald-600">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="banknotes" class="w-4 h-4" /> {{ __('messages.cash_in_hand') }}
        </div>
        <div class="text-2xl font-bold text-emerald-700">{{ \App\Support\Money::format($cashInHand) }}</div>
        <p class="text-xs text-gray-500 mt-1">{{ __('messages.withdrawal_cash_hint') }}</p>
    </div>

    {{-- Withdraw form --}}
    <div class="card mb-4">
        <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
            <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.withdrawal_hint') }}
        </p>
        <form method="POST" action="{{ route('owner.withdrawals.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="label">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input">
            </div>
            <div>
                <label class="label">{{ __('messages.date') }}</label>
                <input type="date" name="withdrawn_at" value="{{ old('withdrawn_at', $today) }}" required class="input">
            </div>
            <div>
                <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn-purchase w-full">
                <x-icon name="building-library" class="w-5 h-5" /> {{ __('messages.withdraw_profit') }}
            </button>
        </form>
    </div>

    {{-- Withdrawal list --}}
    <div class="space-y-3">
        @forelse($withdrawals as $withdrawal)
            <div class="card flex items-start justify-between gap-2">
                <div>
                    @if($withdrawal->note)
                        <div class="font-medium flex items-center gap-1.5">
                            <x-icon name="document-text" class="w-4 h-4 text-gray-400" /> {{ $withdrawal->note }}
                        </div>
                    @endif
                    <div class="text-sm text-gray-500 flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ $withdrawal->withdrawn_at->format('d M Y') }}
                    </div>
                </div>
                <div class="text-lg font-bold shrink-0">{{ \App\Support\Money::format((float) $withdrawal->amount) }}</div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="building-library" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $withdrawals->links() }}</div>
@endsection
