@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
    {{-- Gradient hero: running period status --}}
    @if($openPeriod)
        <a href="{{ route('owner.commissions.close_preview') }}" class="block bg-brand-gradient text-white rounded-2xl shadow-lg shadow-emerald-600/25 p-4 mb-4 active:scale-[0.99] transition">
            <div class="flex items-center justify-between gap-2 mb-1">
                <h2 class="font-bold flex items-center gap-2">
                    <x-icon name="check-circle" class="w-5 h-5" /> {{ __('messages.current_period') }}{{ $openPeriod->label ? ' — '.$openPeriod->label : '' }}
                </h2>
                <span class="text-xs px-2.5 py-1 rounded-full bg-white/20 ring-1 ring-white/25 font-semibold whitespace-nowrap">
                    {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                </span>
            </div>
            <div class="text-sm text-white/85 flex items-center gap-1.5">
                <x-icon name="calendar-days" class="w-4 h-4" /> {{ __('messages.opened_on') }}: {{ $openPeriod->opened_at->format('d M Y') }}
            </div>
            <div class="text-sm font-bold mt-1 flex items-center gap-1.5">
                <x-icon name="lock-closed" class="w-4 h-4" /> {{ __('messages.click_to_close') }}
            </div>
        </a>
    @else
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-amber-500/25 p-4 mb-4 flex items-center justify-between gap-3">
            <div>
                <div class="font-bold flex items-center gap-2">
                    <x-icon name="x-circle" class="w-5 h-5" /> {{ __('messages.no_open_period') }}
                </div>
                <div class="text-sm text-white/85">{{ __('messages.entries_blocked') }}</div>
            </div>
            <a href="{{ route('owner.commissions.index') }}" class="bg-white text-emerald-700 font-bold rounded-xl px-4 py-2.5 text-sm shrink-0 shadow active:scale-95 transition">
                {{ __('messages.open_period_now') }}
            </a>
        </div>
    @endif

    {{-- Quick actions (right under the nav) --}}
    <div class="grid grid-cols-3 gap-2">
        <a href="{{ route('entries.create', ['type' => 'sales']) }}" class="btn-sale px-2 flex-col text-center gap-1">
            <x-icon name="banknotes" class="w-5 h-5" /> {{ __('messages.add_sale') }}
        </a>
        <a href="{{ route('entries.create', ['type' => 'purchases']) }}" class="btn-purchase px-2 flex-col text-center gap-1">
            <x-icon name="shopping-cart" class="w-5 h-5" /> {{ __('messages.add_purchase') }}
        </a>
        <a href="{{ route('entries.create', ['type' => 'expenses']) }}" class="btn-expense px-2 flex-col text-center gap-1">
            <x-icon name="currency-dollar" class="w-5 h-5" /> {{ __('messages.add_expense') }}
        </a>
    </div>
    <div class="grid grid-cols-4 gap-2 mt-2">
        <a href="{{ route('owner.investments.index') }}" class="btn-primary px-2 flex-col text-center gap-1 text-xs">
            <x-icon name="plus-circle" class="w-5 h-5" /> {{ __('messages.invest') }}
        </a>
        <a href="{{ route('owner.commissions.index') }}" class="btn-payout px-2 flex-col text-center gap-1 text-xs">
            <x-icon name="receipt-percent" class="w-5 h-5" /> {{ __('messages.payout_requests') }}
        </a>
        <a href="{{ route('owner.withdrawals.index') }}" class="btn-purchase px-2 flex-col text-center gap-1 text-xs">
            <x-icon name="building-library" class="w-5 h-5" /> {{ __('messages.withdraw_profit') }}
        </a>
        <a href="{{ route('owner.reports.index') }}" class="btn-neutral px-2 flex-col text-center gap-1 text-xs">
            <x-icon name="chart-bar" class="w-5 h-5" /> {{ __('messages.reports') }}
        </a>
    </div>

    {{-- Pending confirmations alert: each chip leads to the right page --}}
    @php
        $totalPending = $pending['expenses'] + $pending['purchases'] + $pending['sales'] + $pending['productions'] + $pending['stock_losses'];
        $entryPending = $pending['expenses'] + $pending['purchases'] + $pending['sales'];
    @endphp
    @if($totalPending > 0)
        <div class="card mt-4 border-l-4 border-l-amber-400">
            <div class="font-bold mb-2 flex items-center gap-1.5">
                <x-icon name="clock" class="w-5 h-5 text-amber-600" /> {{ __('messages.pending_confirmations') }}
            </div>
            <div class="flex flex-wrap gap-2">
                @if($entryPending > 0)
                    <a href="{{ route('owner.entries.index', ['status' => 'pending']) }}"
                       class="filter-pill text-amber-700">
                        <x-icon name="currency-dollar" class="w-4 h-4" /> {{ $pending['expenses'] }}
                        <x-icon name="shopping-cart" class="w-4 h-4" /> {{ $pending['purchases'] }}
                        <x-icon name="banknotes" class="w-4 h-4" /> {{ $pending['sales'] }}
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @endif
                @if($pending['productions'] > 0)
                    <a href="{{ route('owner.productions.index') }}" class="filter-pill text-amber-700">
                        <x-icon name="building-office-2" class="w-4 h-4" /> {{ $pending['productions'] }}
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @endif
                @if($pending['stock_losses'] > 0)
                    <a href="{{ route('owner.stock-losses.index') }}" class="filter-pill text-amber-700">
                        <x-icon name="arrow-trending-down" class="w-4 h-4" /> {{ $pending['stock_losses'] }}
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @endif
            </div>
        </div>
    @endif
    @if($pendingPayoutRequests > 0)
        <a href="{{ route('owner.commissions.index') }}" class="card mt-3 flex items-center justify-between gap-2 border-l-4 border-l-orange-400">
            <span class="text-sm font-semibold text-orange-700 flex items-center gap-1.5">
                <x-icon name="receipt-percent" class="w-5 h-5" /> {{ trans_choice(__('messages.pending_payout_requests'), $pendingPayoutRequests, ['count' => $pendingPayoutRequests]) }}
            </span>
            <x-icon name="arrow-right" class="w-4 h-4 text-orange-400" />
        </a>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
        <x-stat-card :label="__('messages.total_investment')" :value="\App\Support\Money::format($stats['investment'])" icon="banknotes" color="emerald" />
        <x-stat-card :label="__('messages.total_purchase')" :value="\App\Support\Money::format($stats['purchase'])" icon="shopping-cart" color="orange" />
        <x-stat-card :label="__('messages.total_expense')" :value="\App\Support\Money::format($stats['expense'])" icon="currency-dollar" color="rose" />
        <x-stat-card :label="__('messages.total_sales')" :value="\App\Support\Money::format($stats['sales'])" icon="banknotes" color="blue" />
        <x-stat-card :label="__('messages.total_commission').($commissionEstimated ? ' '.__('messages.estimated_hint') : '')" :value="\App\Support\Money::format($stats['commission'])" icon="receipt-percent" color="violet" :sub="$commissionEstimated && $totalRate > 100 ? '<span class=\'text-red-600\'>'.__('messages.rate_over_100', ['total' => number_format($totalRate, 2)]).'</span>' : ''" />
        <x-stat-card :label="__('messages.total_payout')" :value="\App\Support\Money::format($stats['payout'])" icon="credit-card" color="slate" />
        <x-stat-card :label="__('messages.net_profit')" :value="\App\Support\Money::format($stats['net_profit'])" icon="arrow-trending-up" :color="$stats['net_profit'] >= 0 ? 'emerald' : 'rose'" :valueClass="$stats['net_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600'" />
        <x-stat-card :label="__('messages.stock_value')" :value="\App\Support\Money::format($stats['stock_value'])" icon="building-storefront" color="teal" />
        <x-stat-card :label="__('messages.cost_of_goods_sold')" :value="\App\Support\Money::format($stats['cogs'])" icon="tag" color="indigo" />
        <x-stat-card :label="__('messages.cash_in_hand')" :value="\App\Support\Money::format($cashInHand)" icon="building-library" color="emerald" :sub="__('messages.cash_pool_hint').' <a href=\''.route('owner.investments.index').'\' class=\'inline-block mt-1 text-emerald-700 font-bold\'>'.__('messages.add_cash').'</a>'" />
        <x-stat-card :label="__('messages.cash_with_owner')" :value="\App\Support\Money::format($cashInHand - $cashWithPartners)" icon="user-circle" color="emerald" />
        <x-stat-card :label="__('messages.cash_with_partners')" :value="\App\Support\Money::format($cashWithPartners)" icon="receipt-percent" color="cyan" :sub="'<a href=\''.route('owner.partner-cash.index').'\' class=\'inline-block mt-1 text-blue-700 font-bold\'>'.__('messages.transactions').'</a>'" />
    </div>

    {{-- Partner leaderboard --}}
    <div class="card mt-4 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 font-bold flex items-center gap-2">
            <x-icon name="user-group" class="w-5 h-5 text-emerald-600" /> {{ __('messages.leaderboard') }}
        </div>
        @if($leaderboard->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_partners') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($leaderboard as $partner)
                    <li class="px-4 py-3 {{ $partner['is_active'] ? '' : 'bg-gray-50 opacity-60' }}">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="font-medium truncate inline-flex items-center gap-1.5 {{ $partner['is_active'] ? '' : 'line-through text-gray-400' }}">
                                <x-avatar :user="$partner['user']" size="24" /> {{ $partner['name'] }}
                                @unless($partner['is_active'])
                                    <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-500 no-underline" style="text-decoration:none">{{ __('messages.inactive') }}</span>
                                @endunless
                            </div>
                            <div class="text-xl font-bold text-violet-600 shrink-0">{{ $partner['commission_rate'] }}%</div>
                        </div>
                        <div class="text-xs text-gray-500">
                            <x-icon name="banknotes" class="w-3.5 h-3.5" /> {{ __('messages.total_sales') }} {{ \App\Support\Money::format($partner['total_sales']) }}
                            · <x-icon name="shopping-cart" class="w-3.5 h-3.5" /> {{ __('messages.total_purchase') }} {{ \App\Support\Money::format($partner['total_purchase']) }}
                            · <x-icon name="currency-dollar" class="w-3.5 h-3.5" /> {{ __('messages.total_expense') }} {{ \App\Support\Money::format($partner['total_expense']) }}
                            · <x-icon name="receipt-percent" class="w-3.5 h-3.5" /> {{ __('messages.total_commission') }}{{ $commissionEstimated ? ' '.__('messages.estimated_hint') : '' }}:
                            {{ \App\Support\Money::format($partner['commission']) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
