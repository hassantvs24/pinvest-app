@extends('layouts.app')

@section('title', __('messages.total_payout'))

@section('content')
    <div class="card mb-4 border-l-4 border-l-slate-500">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="credit-card" class="w-4 h-4" /> {{ __('messages.total_payout') }}
        </div>
        <div class="text-2xl font-bold">{{ \App\Support\Money::format($total) }}</div>
    </div>

    {{-- Payouts are created automatically when the owner approves a partner's
         payout request — see the Commissions page. --}}
    <a href="{{ route('owner.commissions.index') }}" class="btn-payout w-full mb-4">
        <x-icon name="receipt-percent" class="w-5 h-5" /> {{ __('messages.payout_requests') }}
    </a>
    <p class="text-xs text-gray-500 mb-4 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.payout_history_hint') }}
    </p>

    {{-- Payout history --}}
    <div class="space-y-3">
        @forelse($payouts as $payout)
            <div class="card flex items-start justify-between gap-2">
                <div>
                    <div class="font-medium inline-flex items-center gap-1.5"><x-avatar :user="$payout->user" size="22" /> {{ $payout->user->name ?? '—' }}</div>
                    @if($payout->note)
                        <div class="text-sm text-gray-500 flex items-center gap-1.5">
                            <x-icon name="document-text" class="w-4 h-4" /> {{ $payout->note }}
                        </div>
                    @endif
                    <div class="text-sm text-gray-500 flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ $payout->payout_date->format('d M Y') }}
                    </div>
                </div>
                <div class="text-lg font-bold shrink-0">{{ \App\Support\Money::format((float) $payout->amount) }}</div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="credit-card" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $payouts->links() }}</div>
@endsection
