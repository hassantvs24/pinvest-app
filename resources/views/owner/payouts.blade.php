@extends('layouts.app')

@section('title', __('messages.total_payout'))

@section('content')
    <div class="bg-white rounded-xl shadow p-4 mb-4 border-l-4 border-slate-600">
        <div class="text-sm text-gray-500">💳 {{ __('messages.total_payout') }}</div>
        <div class="text-2xl font-bold">{{ \App\Support\Money::format($total) }}</div>
    </div>

    {{-- Payouts are created automatically when the owner approves a partner's
         payout request — see the Commissions page. --}}
    <a href="{{ route('owner.commissions.index') }}"
       class="block w-full bg-violet-600 hover:bg-violet-700 text-white text-center font-bold rounded-xl py-3 mb-4">
        💸 {{ __('messages.payout_requests') }}
    </a>
    <p class="text-xs text-gray-500 mb-4">💡 {{ __('messages.payout_history_hint') }}</p>

    {{-- Payout history --}}
    <div class="space-y-3">
        @forelse($payouts as $payout)
            <div class="bg-white rounded-xl shadow p-4 flex items-start justify-between gap-2">
                <div>
                    <div class="font-medium inline-flex items-center gap-1.5"><x-avatar :user="$payout->user" size="22" /> {{ $payout->user->name ?? '—' }}</div>
                    @if($payout->note)
                        <div class="text-sm text-gray-500">📝 {{ $payout->note }}</div>
                    @endif
                    <div class="text-sm text-gray-500">📅 {{ $payout->payout_date->format('d M Y') }}</div>
                </div>
                <div class="text-lg font-bold shrink-0">{{ \App\Support\Money::format((float) $payout->amount) }}</div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">💳 {{ __('messages.no_entries') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $payouts->links() }}</div>
@endsection
