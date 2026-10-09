@extends('layouts.app')

@section('title', __('messages.total_investment'))

@section('content')
    <div class="bg-white rounded-xl shadow p-4 mb-4 border-l-4 border-emerald-700">
        <div class="text-sm text-gray-500">💰 {{ __('messages.total_investment') }}</div>
        <div class="text-2xl font-bold">৳{{ number_format($total, 2) }}</div>
    </div>

    {{-- Add investment form --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4">
        <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.investment_cash_hint') }}</p>
        <form method="POST" action="{{ route('owner.investments.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.date') }}</label>
                <input type="date" name="invested_at" value="{{ old('invested_at', now()->format('Y-m-d')) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl py-3">
                ➕ {{ __('messages.add_investment') }}
            </button>
        </form>
    </div>

    {{-- Investment list --}}
    <div class="space-y-3">
        @forelse($investments as $investment)
            <div class="bg-white rounded-xl shadow p-4 flex items-start justify-between gap-2">
                <div>
                    @if($investment->note)
                        <div class="font-medium">📝 {{ $investment->note }}</div>
                    @endif
                    <div class="text-sm text-gray-500">📅 {{ $investment->invested_at->format('d M Y') }}</div>
                </div>
                <div class="text-lg font-bold shrink-0">৳{{ number_format((float) $investment->amount, 2) }}</div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">💰 {{ __('messages.no_entries') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $investments->links() }}</div>
@endsection
