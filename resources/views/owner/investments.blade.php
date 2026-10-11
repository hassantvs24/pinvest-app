@extends('layouts.app')

@section('title', __('messages.total_investment'))

@section('content')
    <div class="card mb-4 border-l-4 border-l-emerald-600">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="banknotes" class="w-4 h-4" /> {{ __('messages.total_investment') }}
        </div>
        <div class="text-2xl font-bold">{{ \App\Support\Money::format($total) }}</div>
    </div>

    {{-- Add investment form --}}
    <div class="card mb-4">
        <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
            <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.investment_cash_hint') }}
        </p>
        <form method="POST" action="{{ route('owner.investments.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="label">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input">
            </div>
            <div>
                <label class="label">{{ __('messages.date') }}</label>
                <input type="date" name="invested_at" value="{{ old('invested_at', now()->format('Y-m-d')) }}" required class="input">
            </div>
            <div>
                <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn-primary w-full">
                <x-icon name="plus" class="w-5 h-5" /> {{ __('messages.add_investment') }}
            </button>
        </form>
    </div>

    {{-- Investment list --}}
    <div class="space-y-3">
        @forelse($investments as $investment)
            <div class="card flex items-start justify-between gap-2">
                <div>
                    @if($investment->note)
                        <div class="font-medium flex items-center gap-1.5">
                            <x-icon name="document-text" class="w-4 h-4 text-gray-400" /> {{ $investment->note }}
                        </div>
                    @endif
                    <div class="text-sm text-gray-500 flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ $investment->invested_at->format('d M Y') }}
                    </div>
                </div>
                <div class="text-lg font-bold shrink-0">{{ \App\Support\Money::format((float) $investment->amount) }}</div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="banknotes" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $investments->links() }}</div>
@endsection
