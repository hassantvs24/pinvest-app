@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
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

    {{-- Cycle status (read-only) --}}
    @if($openPeriod)
        <div class="bg-brand-gradient text-white rounded-2xl shadow-lg shadow-emerald-600/25 p-4 mt-4">
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
            <div class="text-xs text-white/75 mt-1 flex items-center gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4" /> {{ __('messages.partner_period_note') }}
            </div>
        </div>
    @else
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-amber-500/25 p-4 mt-4">
            <div class="font-bold flex items-center gap-2">
                <x-icon name="x-circle" class="w-5 h-5" /> {{ __('messages.no_open_period') }}
            </div>
            <div class="text-sm text-white/85">{{ __('messages.partner_no_period_wait') }}</div>
        </div>
    @endif

    {{-- Pending alert: each chip leads to the right list --}}
    @if($pendingCount > 0)
        <div class="card mt-4 border-l-4 border-l-amber-400">
            <div class="font-bold mb-2 flex items-center gap-1.5">
                <x-icon name="clock" class="w-5 h-5 text-amber-600" /> {{ __('messages.pending_entries') }}: {{ $pendingCount }}
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    ['n' => $pendingBreakdown['expenses'], 'icon' => 'currency-dollar', 'url' => route('entries.index', ['type' => 'expenses', 'status' => 'pending'])],
                    ['n' => $pendingBreakdown['purchases'], 'icon' => 'shopping-cart', 'url' => route('entries.index', ['type' => 'purchases', 'status' => 'pending'])],
                    ['n' => $pendingBreakdown['sales'], 'icon' => 'banknotes', 'url' => route('entries.index', ['type' => 'sales', 'status' => 'pending'])],
                    ['n' => $pendingBreakdown['productions'], 'icon' => 'building-office-2', 'url' => route('productions.index')],
                    ['n' => $pendingBreakdown['stock_losses'], 'icon' => 'arrow-trending-down', 'url' => route('stock-losses.index')],
                ] as $chip)
                    @if($chip['n'] > 0)
                        <a href="{{ $chip['url'] }}" class="filter-pill text-amber-700">
                            <x-icon :name="$chip['icon']" class="w-4 h-4" /> {{ $chip['n'] }}
                            <x-icon name="arrow-right" class="w-4 h-4" />
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- My summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
        <x-stat-card :label="__('messages.my_sales')" :value="\App\Support\Money::format($stats['sales'])" icon="banknotes" color="blue" />
        <x-stat-card :label="__('messages.my_commission').($commissionEstimated ? ' '.__('messages.estimated_hint') : '')" :value="\App\Support\Money::format($stats['commission'])" icon="receipt-percent" color="violet" :sub="$commissionRate.'% · '.__('messages.commission_due').': <a href=\''.route('commissions.index').'\' class=\'font-bold text-orange-600 underline\'>'.\App\Support\Money::format($pendingCommission).'</a>'" />
        <x-stat-card :label="__('messages.my_purchase')" :value="\App\Support\Money::format($stats['purchase'])" icon="shopping-cart" color="orange" />
        <x-stat-card :label="__('messages.my_expense')" :value="\App\Support\Money::format($stats['expense'])" icon="currency-dollar" color="rose" />
        <x-stat-card :label="__('messages.my_held_cash')" :value="\App\Support\Money::format($heldCash)" icon="building-library" color="cyan" :valueClass="$heldCash < 0 ? 'text-rose-600' : 'text-cyan-700'" :sub="__('messages.my_held_cash_hint')" />
    </div>

    {{-- Last cycle commission --}}
    @if($lastSettlement)
        <a href="{{ route('commissions.index') }}" class="card card-hover mt-4 flex items-center justify-between gap-3 border-l-4 border-l-violet-500">
            <div class="min-w-0">
                <div class="text-sm text-gray-500 flex items-center gap-1.5">
                    <x-icon name="calendar-days" class="w-4 h-4" /> {{ __('messages.last_cycle_commission') }}
                </div>
                <div class="font-bold truncate">
                    {{ $lastSettlement->period?->label ?: $lastSettlement->period_start->format('M Y') }}
                    · {{ $lastSettlement->period_start->format('d M') }} – {{ $lastSettlement->period_end->format('d M Y') }}
                </div>
                <div class="text-xs text-gray-500">
                    <x-icon name="arrow-trending-up" class="w-3.5 h-3.5" /> {{ __('messages.period_profit') }}: {{ \App\Support\Money::format((float) $lastSettlement->business_profit) }}
                    · <x-icon name="receipt-percent" class="w-3.5 h-3.5" /> {{ $lastSettlement->commission_rate }}%
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-xl font-bold text-gray-900">{{ \App\Support\Money::format((float) $lastSettlement->amount) }}</div>
                @if($lastSettlement->status === 'paid')
                    <span class="badge badge-confirmed"><x-icon name="check" class="w-3.5 h-3.5" /> {{ __('messages.paid') }}</span>
                @else
                    <span class="badge badge-pending"><x-icon name="clock" class="w-3.5 h-3.5" /> {{ __('messages.pending') }}</span>
                @endif
            </div>
        </a>
    @endif
@endsection
