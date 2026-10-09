@extends('layouts.app')

@section('title', __('messages.withdraw_profit'))

@section('content')
    <div class="bg-white rounded-xl shadow p-4 mb-4 border-l-4 border-amber-600">
        <div class="text-sm text-gray-500">🏦 {{ __('messages.total_withdrawn') }}</div>
        <div class="text-2xl font-bold">৳{{ number_format($total, 2) }}</div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 mb-4 border-l-4 border-emerald-700">
        <div class="text-sm text-gray-500">💵 {{ __('messages.cash_in_hand') }}</div>
        <div class="text-2xl font-bold text-emerald-700">৳{{ number_format($cashInHand, 2) }}</div>
        <p class="text-xs text-gray-500 mt-1">{{ __('messages.withdrawal_cash_hint') }}</p>
    </div>

    {{-- Withdraw form --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4">
        <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.withdrawal_hint') }}</p>
        <form method="POST" action="{{ route('owner.withdrawals.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.date') }}</label>
                <input type="date" name="withdrawn_at" value="{{ old('withdrawn_at', $today) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl py-3">
                🏦 {{ __('messages.withdraw_profit') }}
            </button>
        </form>
    </div>

    {{-- Withdrawal list --}}
    <div class="space-y-3">
        @forelse($withdrawals as $withdrawal)
            <div class="bg-white rounded-xl shadow p-4 flex items-start justify-between gap-2">
                <div>
                    @if($withdrawal->note)
                        <div class="font-medium">📝 {{ $withdrawal->note }}</div>
                    @endif
                    <div class="text-sm text-gray-500">📅 {{ $withdrawal->withdrawn_at->format('d M Y') }}</div>
                </div>
                <div class="text-lg font-bold shrink-0">৳{{ number_format((float) $withdrawal->amount, 2) }}</div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">🏦 {{ __('messages.no_entries') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $withdrawals->links() }}</div>
@endsection
