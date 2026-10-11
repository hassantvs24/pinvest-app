@extends('layouts.app')

@section('title', __('messages.commissions'))

@section('content')
    {{-- Rate total hint --}}
    @if($totalRate > 100)
        <div class="alert-error mb-4">
            <x-icon name="exclamation-triangle" class="w-5 h-5 shrink-0" /> {{ __('messages.rate_over_100', ['total' => number_format($totalRate, 2)]) }}
        </div>
    @endif

    {{-- 1. Open period / close period --}}
    @if($openPeriod)
        <div class="bg-brand-gradient text-white rounded-2xl shadow-lg shadow-emerald-600/25 p-4 mb-6">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-bold flex items-center gap-2">
                    <x-icon name="check-circle" class="w-5 h-5" />
                    {{ __('messages.current_period') }}{{ $openPeriod->label ? ' — '.$openPeriod->label : '' }}
                </h2>
                <span class="text-xs px-2.5 py-1 rounded-full bg-white/20 ring-1 ring-white/25 font-semibold whitespace-nowrap">
                    {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                </span>
            </div>
            <ul class="text-sm text-white/90 space-y-1 mb-3">
                <li class="flex items-center gap-1.5"><x-icon name="calendar-days" class="w-4 h-4" /> {{ __('messages.opened_on') }}: {{ \App\Support\DateFormats::dateTime($openPeriod->opened_at) }}</li>
                <li class="flex items-center gap-1.5"><x-icon name="building-library" class="w-4 h-4" /> {{ __('messages.cash_in_hand') }}: {{ \App\Support\Money::format($cashInHand) }}</li>
                @if($openPeriod->note)
                    <li class="flex items-center gap-1.5"><x-icon name="document-text" class="w-4 h-4" /> {{ $openPeriod->note }}</li>
                @endif
            </ul>

            <a href="{{ route('owner.commissions.close_preview') }}" class="btn w-full bg-white text-emerald-700 shadow-md hover:bg-emerald-50">
                <x-icon name="lock-closed" class="w-5 h-5" /> {{ __('messages.close_period') }}
            </a>
            <p class="text-xs text-white/75 mt-2 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.close_period_hint') }}
            </p>
        </div>
    @else
        <div class="card mb-6">
            <h2 class="font-bold mb-1 flex items-center gap-2">
                <x-icon name="check-circle" class="w-5 h-5 text-emerald-600" /> {{ __('messages.open_period') }}
            </h2>
            <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.open_period_hint') }}
            </p>
            <p class="text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-3 py-2 mb-3 flex items-center gap-1.5">
                <x-icon name="clock" class="w-4 h-4" /> {{ __('messages.auto_datetime_hint') }}
            </p>
            <form method="POST" action="{{ route('owner.commissions.open') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label text-xs text-gray-500">{{ __('messages.period_label') }} {{ __('messages.optional') }}</label>
                    <input type="text" name="label" value="{{ old('label') }}" placeholder="{{ __('messages.period_label_placeholder') }}" class="input-sm">
                </div>
                <div>
                    <label class="label text-xs text-gray-500"><x-icon name="banknotes" class="w-3.5 h-3.5" /> {{ __('messages.opening_investment') }} {{ __('messages.optional') }}</label>
                    <input type="number" name="investment_amount" step="0.01" min="0.01" value="{{ old('investment_amount') }}" class="input-sm">
                </div>
                <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 flex items-start gap-1.5">
                    <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.opening_investment_hint') }}
                </p>
                <div>
                    <label class="label text-xs text-gray-500">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="input-sm">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn-primary w-full">
                    <x-icon name="check-circle" class="w-5 h-5" /> {{ __('messages.open_period') }}
                </button>
            </form>
        </div>
    @endif

    {{-- 2. Pending payout requests --}}
    <h2 class="section-title"><x-icon name="receipt-percent" class="w-5 h-5 text-violet-600" /> {{ __('messages.payout_requests') }}</h2>
    <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.payout_requests_hint') }}
    </p>
    <div class="space-y-3 mb-6">
        @forelse($requests as $request)
            <div class="card">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="font-bold inline-flex items-center gap-1.5"><x-avatar :user="$request->user" size="24" /> {{ $request->user->name }}</div>
                        <div class="text-sm text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-4 h-4" /> {{ $request->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="text-xl font-bold text-violet-700 shrink-0">{{ \App\Support\Money::format((float) $request->amount) }}</div>
                </div>
                <div class="flex gap-2 mt-3">
                    <form method="POST" action="{{ route('owner.commissions.approve', ['request' => $request->id]) }}" class="js-confirm-approve">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-icon bg-green-600 text-white shadow-md shadow-green-600/25 hover:bg-green-700" title="{{ __('messages.approve_pay') }}">
                            <x-icon name="check" class="w-5 h-5" />
                        </button>
                    </form>
                    <form method="POST" action="{{ route('owner.commissions.reject', ['request' => $request->id]) }}" class="js-confirm-reject">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-icon bg-red-500 text-white shadow-md shadow-red-500/25 hover:bg-red-600" title="{{ __('messages.reject') }}">
                            <x-icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state mb-6">
                <x-icon name="receipt-percent" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_payout_requests') }}
            </div>
        @endforelse
    </div>

    {{-- 3. Closed periods --}}
    <div class="flex items-center justify-between mb-2">
        <h2 class="section-title mb-0"><x-icon name="archive-box" class="w-5 h-5 text-emerald-600" /> {{ __('messages.period_history') }}</h2>
        <a href="{{ route('owner.withdrawals.index') }}" class="btn-purchase">
            <x-icon name="building-library" class="w-4 h-4" /> {{ __('messages.withdraw_profit') }}
        </a>
    </div>
    <div class="space-y-3 mb-6">
        @forelse($closedPeriods as $period)
            <div class="card">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="font-bold flex items-center gap-1.5">
                            <x-icon name="lock-closed" class="w-4 h-4 text-slate-500" /> {{ $period->label ?: $period->opened_at->format('M Y') }}
                        </div>
                        <div class="text-sm text-gray-500 flex items-center gap-1.5">
                            <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($period->opened_at) }} – {{ \App\Support\DateFormats::dateTime($period->closed_at) }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold {{ ($period->profit ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ \App\Support\Money::format((float) $period->profit) }}
                        </div>
                        <div class="text-xs text-gray-500">
                            @if(($period->profit ?? 0) >= 0)
                                <x-icon name="arrow-trending-up" class="w-3.5 h-3.5" /> {{ __('messages.net_profit') }} · <x-icon name="receipt-percent" class="w-3.5 h-3.5" /> ×{{ $period->settlements_count }}
                            @else
                                <x-icon name="arrow-trending-down" class="w-3.5 h-3.5" /> {{ __('messages.period_loss_label') }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state mb-6">
                <x-icon name="archive-box" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_periods') }}
            </div>
        @endforelse
    </div>

    {{-- 4. Settlements list --}}
    <h2 class="section-title"><x-icon name="clipboard-document-list" class="w-5 h-5 text-emerald-600" /> {{ __('messages.commission_settlements') }}</h2>
    <div class="space-y-3">
        @forelse($settlements as $settlement)
            <div class="card flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-bold inline-flex items-center gap-1.5"><x-avatar :user="$settlement->user" size="22" /> {{ $settlement->user->name }}</div>
                    <div class="text-sm text-gray-500 flex items-center gap-1.5 flex-wrap">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ $settlement->period?->label ?: \App\Support\DateFormats::dateTime($settlement->period_start).' – '.\App\Support\DateFormats::dateTime($settlement->period_end) }}
                        · <x-icon name="arrow-trending-up" class="w-3.5 h-3.5" /> {{ \App\Support\Money::format((float) $settlement->business_profit) }}
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
