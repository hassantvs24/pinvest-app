@extends('layouts.app')

@section('title', __('messages.my_commission'))

@section('content')
    <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.my_commissions_hint') }}
    </p>

    {{-- Pending due + request button --}}
    <div class="card mb-4 border-l-4 border-l-violet-500">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="receipt-percent" class="w-4 h-4" /> {{ __('messages.commission_due') }}
        </div>
        <div class="text-3xl font-bold {{ $pendingDue > 0 ? 'text-orange-600' : 'text-green-600' }}">
            {{ \App\Support\Money::format($pendingDue) }}
        </div>
        <div class="text-xs text-gray-500 mt-1">
            {{ __('messages.total') }} {{ __('messages.my_commission') }}: {{ \App\Support\Money::format($earnedTotal) }}
        </div>

        @if($pendingDue > 0)
            @if($hasPendingRequest)
                <div class="mt-3 alert-warning">
                    <x-icon name="clock" class="w-5 h-5 shrink-0" /> {{ __('messages.request_pending_note') }}
                </div>
            @else
                <form method="POST" action="{{ route('commissions.request') }}" class="js-confirm-submit mt-3">
                    @csrf
                    <button type="submit" class="btn-payout w-full">
                        <x-icon name="receipt-percent" class="w-5 h-5" /> {{ __('messages.request_payout') }}
                    </button>
                </form>
            @endif
        @endif
    </div>

    {{-- Settlements list --}}
    <h2 class="section-title"><x-icon name="calendar-days" class="w-5 h-5 text-emerald-600" /> {{ __('messages.commission_history') }}</h2>
    <div class="space-y-3">
        @forelse($settlements as $settlement)
            <div class="card flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-medium flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4 text-gray-400" /> {{ $settlement->period?->label ?: $settlement->period_start->format('d M Y').' – '.$settlement->period_end->format('d M Y') }}
                    </div>
                    <div class="text-sm text-gray-500">
                        <x-icon name="arrow-trending-up" class="w-3.5 h-3.5" /> {{ __('messages.period_profit') }}: {{ \App\Support\Money::format((float) $settlement->business_profit) }}
                        · <x-icon name="receipt-percent" class="w-3.5 h-3.5" /> {{ $settlement->commission_rate }}%
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-bold">{{ \App\Support\Money::format((float) $settlement->amount) }}</div>
                    @if($settlement->status === 'paid')
                        <span class="badge badge-confirmed"><x-icon name="check" class="w-3.5 h-3.5" /> {{ __('messages.paid') }}</span>
                    @else
                        <span class="badge badge-pending"><x-icon name="clock" class="w-3.5 h-3.5" /> {{ __('messages.pending') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="receipt-percent" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_commission') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $settlements->links() }}</div>
@endsection
