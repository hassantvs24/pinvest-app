@extends('layouts.app')

@section('title', __('messages.reports'))

@section('content')
    {{-- Date range filter + presets --}}
    <form method="GET" action="{{ route('owner.reports.index') }}" class="card mb-4 print:hidden">
        <div class="mb-3">
            <label class="label text-xs text-gray-500"><x-icon name="arrow-path" class="w-3.5 h-3.5" /> {{ __('messages.cycle') }}</label>
            <select name="cycle" onchange="this.form.submit()" class="input-sm">
                <option value="">{{ __('messages.all_cycles') }}</option>
                @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}" @selected($selectedCycle?->id === $cycle->id)>
                        {{ $cycle->label ?: $cycle->opened_at->format('d M Y') }}
                        ({{ $cycle->opened_at->format('d M Y') }} – {{ $cycle->closed_at?->format('d M Y') ?? __('messages.status_open') }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label text-xs text-gray-500"><x-icon name="calendar-days" class="w-3.5 h-3.5" /> {{ __('messages.from_date') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="input-sm">
            </div>
            <div>
                <label class="label text-xs text-gray-500"><x-icon name="calendar-days" class="w-3.5 h-3.5" /> {{ __('messages.to_date') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="input-sm">
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn-primary">
                <x-icon name="magnifying-glass" class="w-5 h-5" /> {{ __('messages.filter') }}
            </button>
            <a href="{{ route('owner.reports.index', ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" class="btn-neutral">{{ __('messages.this_month') }}</a>
            <a href="{{ route('owner.reports.index', ['from' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'to' => now()->subMonth()->endOfMonth()->format('Y-m-d')]) }}" class="btn-neutral">{{ __('messages.last_month') }}</a>
            <a href="{{ route('owner.reports.index') }}" class="btn-neutral">{{ __('messages.all_time') }}</a>
            <button type="button" onclick="window.print()" class="btn text-white bg-slate-600 shadow-md shadow-slate-600/25 hover:bg-slate-700">
                <x-icon name="printer" class="w-5 h-5" /> {{ __('messages.print') }}
            </button>
        </div>
    </form>

    {{-- Cycle meta --}}
    @if($selectedCycle)
        <div class="card mb-4 border-l-4 border-l-emerald-600">
            <div class="flex items-center justify-between mb-1">
                <div class="font-bold flex items-center gap-1.5">
                    <x-icon name="arrow-path" class="w-4 h-4 text-emerald-600" /> {{ __('messages.cycle') }}: {{ $selectedCycle->label ?: $selectedCycle->opened_at->format('d M Y') }}
                </div>
                @if($selectedCycle->status === 'open')
                    <span class="text-xs font-bold text-green-700 flex items-center gap-1"><x-icon name="check-circle" class="w-4 h-4" /> {{ __('messages.status_open') }}</span>
                @else
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1"><x-icon name="lock-closed" class="w-4 h-4" /> {{ __('messages.status_closed') }}</span>
                @endif
            </div>
            <div class="text-sm text-gray-600 flex items-center gap-1.5 flex-wrap">
                <x-icon name="calendar-days" class="w-4 h-4" /> {{ $selectedCycle->opened_at->format('d M Y') }} – {{ $selectedCycle->closed_at?->format('d M Y') ?? now()->format('d M Y') }}
                · {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                @if($selectedCycle->note)
                    · <x-icon name="chat-bubble-left" class="w-4 h-4" /> {{ $selectedCycle->note }}
                @endif
            </div>
        </div>
    @endif

    {{-- Business health: what needs your attention --}}
    <h2 class="section-title"><x-icon name="sparkles" class="w-5 h-5 text-emerald-600" /> {{ __('messages.business_health') }}</h2>
    <div class="space-y-2 mb-6">
        @foreach($insights as $insight)
            @php
                $toneClasses = [
                    'danger' => 'border-l-red-500 bg-red-50',
                    'warning' => 'border-l-amber-400 bg-amber-50',
                    'success' => 'border-l-emerald-500 bg-emerald-50',
                ];
                $toneIcon = [
                    'danger' => 'text-red-600',
                    'warning' => 'text-amber-600',
                    'success' => 'text-emerald-600',
                ];
            @endphp
            <div class="card border-l-4 {{ $toneClasses[$insight['tone']] }} py-2.5">
                <div class="flex items-start justify-between gap-3 text-sm">
                    <div class="flex items-start gap-2">
                        <x-icon :name="$insight['icon']" class="w-4 h-4 mt-0.5 {{ $toneIcon[$insight['tone']] }}" />
                        <span class="{{ $insight['tone'] === 'danger' ? 'font-semibold text-red-800' : ($insight['tone'] === 'warning' ? 'text-amber-800' : 'text-emerald-800') }}">{{ $insight['message'] }}</span>
                    </div>
                    @if(!empty($insight['link']))
                        <a href="{{ $insight['link'] }}" class="shrink-0 flex items-center gap-1 font-bold {{ $insight['tone'] === 'danger' ? 'text-red-700' : ($insight['tone'] === 'warning' ? 'text-amber-700' : 'text-emerald-700') }}">
                            {{ $insight['link_label'] }} <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- 1. Business summary for the period --}}
    <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.reports_intro_hint') }}
    </p>
    <h2 class="section-title"><x-icon name="chart-bar" class="w-5 h-5 text-emerald-600" /> {{ __('messages.business_summary') }}
        @if($from || $to)
            <span class="text-sm font-normal text-gray-500">({{ $from ?? '…' }} — {{ $to ?? '…' }})</span>
        @endif
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <x-stat-card :label="__('messages.total_investment')" :value="\App\Support\Money::format($stats['investment'])" icon="banknotes" color="emerald" />
        <x-stat-card :label="__('messages.total_purchase')" :value="\App\Support\Money::format($stats['purchase'])" icon="shopping-cart" color="orange" />
        <x-stat-card :label="__('messages.total_expense')" :value="\App\Support\Money::format($stats['expense'])" icon="currency-dollar" color="rose" />
        <x-stat-card :label="__('messages.total_sales')" :value="\App\Support\Money::format($stats['sales'])" icon="banknotes" color="blue" />
        <x-stat-card :label="__('messages.total_commission')" :value="\App\Support\Money::format($stats['commission'])" icon="receipt-percent" color="violet" />
        <x-stat-card :label="__('messages.total_payout')" :value="\App\Support\Money::format($stats['payout'])" icon="credit-card" color="slate" />
        <x-stat-card :label="__('messages.net_profit')" :value="\App\Support\Money::format($stats['net_profit'])" icon="arrow-trending-up" :color="$stats['net_profit'] >= 0 ? 'emerald' : 'rose'" :valueClass="$stats['net_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600'" />
        <x-stat-card :label="__('messages.owner_share')" :value="\App\Support\Money::format($ownerShare)" icon="user-circle" :color="$ownerShare >= 0 ? 'emerald' : 'rose'" :valueClass="$ownerShare >= 0 ? 'text-emerald-700' : 'text-red-600'" />
        <x-stat-card :label="__('messages.stock_value')" :value="\App\Support\Money::format($stats['stock_value'])" icon="building-storefront" color="teal" />
        <x-stat-card :label="__('messages.cost_of_goods_sold')" :value="\App\Support\Money::format($stats['cogs'])" icon="tag" color="indigo" />
        <x-stat-card :label="__('messages.stock_loss')" :value="\App\Support\Money::format($stats['stock_loss'])" icon="arrow-trending-down" color="red" />
        <x-stat-card :label="__('messages.cash_in_hand').' ('.__('messages.all_time').')'" :value="\App\Support\Money::format($lifetimeCashInHand)" icon="building-library" color="emerald" />
    </div>

    {{-- Key performance ratios --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="card py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('messages.profit_margin') }}</div>
            <div class="text-xl font-extrabold {{ $ratios['profit_margin'] === null ? 'text-gray-400' : ($ratios['profit_margin'] >= 0 ? 'text-emerald-600' : 'text-red-600') }}">
                {{ $ratios['profit_margin'] === null ? '—' : number_format($ratios['profit_margin'], 1).'%' }}
            </div>
            <div class="text-xs text-gray-500">{{ __('messages.profit_margin_hint') }}</div>
        </div>
        <div class="card py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('messages.expense_ratio') }}</div>
            <div class="text-xl font-extrabold {{ $ratios['expense_ratio'] === null ? 'text-gray-400' : ($ratios['expense_ratio'] <= 30 ? 'text-emerald-600' : 'text-amber-600') }}">
                {{ $ratios['expense_ratio'] === null ? '—' : number_format($ratios['expense_ratio'], 1).'%' }}
            </div>
            <div class="text-xs text-gray-500">{{ __('messages.expense_ratio_hint') }}</div>
        </div>
        <div class="card py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('messages.stock_months') }}</div>
            <div class="text-xl font-extrabold {{ $ratios['stock_months'] === null ? 'text-gray-400' : ($ratios['stock_months'] <= 3 ? 'text-emerald-600' : 'text-amber-600') }}">
                {{ $ratios['stock_months'] === null ? '—' : number_format($ratios['stock_months'], 1) }}
            </div>
            <div class="text-xs text-gray-500">{{ __('messages.stock_months_hint') }}</div>
        </div>
        <div class="card py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('messages.cash_after_commission') }}</div>
            <div class="text-xl font-extrabold {{ $ratios['cash_after_commission'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ \App\Support\Money::format($ratios['cash_after_commission']) }}
            </div>
            <div class="text-xs text-gray-500">{{ __('messages.cash_after_commission_hint') }}</div>
        </div>
    </div>

    {{-- 6-month growth trend --}}
    @if($trend->isNotEmpty())
        <h2 class="section-title"><x-icon name="arrow-trending-up" class="w-5 h-5 text-emerald-600" /> {{ __('messages.six_month_trend') }}</h2>
        <div class="card mb-6 overflow-hidden p-0">
            <ul class="divide-y divide-gray-100">
                @foreach($trend as $month)
                    <li class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 text-sm mb-1">
                            <span class="font-bold text-gray-700">{{ $month['label'] }}</span>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-blue-700 font-semibold">{{ __('messages.sales_short') }} {{ \App\Support\Money::format($month['sales']) }}</span>
                                <span class="{{ $month['profit_negative'] ? 'text-red-600' : 'text-emerald-700' }} font-semibold">
                                    {{ __('messages.profit_short') }} {{ \App\Support\Money::format($month['profit']) }}
                                </span>
                                @if($month['margin_rate'] !== null)
                                    <span class="text-gray-500">{{ number_format($month['margin_rate'] * 100, 1) }}%</span>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-1">
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500" style="width: {{ $month['sales_bar'] }}%"></div>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $month['profit_negative'] ? 'bg-gradient-to-r from-red-500 to-rose-500' : 'bg-gradient-to-r from-emerald-500 to-teal-500' }}" style="width: {{ $month['profit_bar'] }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span><x-icon name="shopping-cart" class="w-3 h-3" /> {{ \App\Support\Money::format($month['purchase']) }}</span>
                                <span><x-icon name="currency-dollar" class="w-3 h-3" /> {{ \App\Support\Money::format($month['expense']) }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-4 py-2 bg-gray-50 text-xs text-gray-500 border-t border-gray-100 flex items-center gap-2 flex-wrap">
                <x-icon name="information-circle" class="w-3.5 h-3.5" /> {{ __('messages.trend_hint') }}
            </div>
        </div>
    @endif

    {{-- 2. Investment report --}}
    <h2 class="section-title"><x-icon name="banknotes" class="w-5 h-5 text-emerald-600" /> {{ __('messages.investment_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="banknotes" class="w-4 h-4 text-emerald-600" /> {{ __('messages.total_investment') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['investment']) }}</span>
        </div>
        @if($investments->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($investments as $investment)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $investment->note ?? '—' }}</div>
                            <div class="text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-3.5 h-3.5" /> {{ $investment->invested_at->format('d M Y') }}</div>
                        </div>
                        <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) $investment->amount) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 3. Sales report --}}
    <h2 class="section-title"><x-icon name="banknotes" class="w-5 h-5 text-blue-600" /> {{ __('messages.sales_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="banknotes" class="w-4 h-4 text-blue-600" /> {{ __('messages.total_sales') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['sales']) }}</span>
        </div>
        @if($productPerformance->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($productPerformance as $row)
                    <li class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $row['name'] }}</span>
                                <span class="text-gray-500">{{ \App\Support\ItemUnits::formatQuantity($row['quantity']) }} {{ $row['unit'] }}</span>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-bold">{{ \App\Support\Money::format($row['sales']) }}</div>
                                <div class="text-xs {{ $row['margin'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ __('messages.profit_short') }} {{ \App\Support\Money::format($row['margin']) }} · {{ number_format($row['margin_rate'] * 100, 1) }}%
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-gray-100">
            <div class="px-4 py-2 bg-gray-50 text-xs font-bold text-gray-500 flex items-center gap-1.5"><x-icon name="document-text" class="w-3.5 h-3.5" /> {{ __('messages.entry_details') }}</div>
            @if($salesDetails->isEmpty())
                <div class="px-4 py-4 text-center text-gray-500 text-sm">{{ __('messages.no_entries') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($salesDetails as $entry)
                        <li class="px-4 py-2 flex items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <span class="text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-3 h-3" /> {{ \App\Support\DateFormats::dateTime($entry->entry_date) }}</span>
                                {{ $entry->item->name ?? '—' }}
                                <span class="text-gray-500">× {{ \App\Support\ItemUnits::formatQuantity((float) $entry->quantity) }}</span>
                            </div>
                            <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) $entry->total) }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- 4. Stock report (unified item list) --}}
    <h2 class="section-title"><x-icon name="building-storefront" class="w-5 h-5 text-teal-600" /> {{ __('messages.stock_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        @if($stockRows === [])
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_stock') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($stockRows as $row)
                    <li class="px-4 py-2 flex items-center justify-between text-sm">
                        <div>
                            <span class="font-medium">{{ $row['item']->name }}</span>
                            <span class="text-xs text-emerald-700">
                                {{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($row['item']->unit) }}
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">{{ \App\Support\Money::format($row['value']) }}</div>
                            <div class="text-xs text-gray-500">{{ __('messages.avg_cost') }}: {{ \App\Support\Money::format($row['avg_cost']) }}/{{ \App\Support\ItemUnits::label($row['item']->unit) }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <h2 class="section-title"><x-icon name="shopping-cart" class="w-5 h-5 text-orange-600" /> {{ __('messages.purchase_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="shopping-cart" class="w-4 h-4 text-orange-600" /> {{ __('messages.total_purchase') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['purchase']) }}</span>
        </div>
        @if($purchasesByItem->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($purchasesByItem as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div>{{ $row['name'] }} <span class="text-gray-500">{{ $row['quantity'] }} {{ $row['unit'] }}</span></div>
                        <div class="font-bold shrink-0">{{ \App\Support\Money::format($row['total']) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-gray-100">
            <div class="px-4 py-2 bg-gray-50 text-xs font-bold text-gray-500 flex items-center gap-1.5"><x-icon name="document-text" class="w-3.5 h-3.5" /> {{ __('messages.entry_details') }}</div>
            @if($purchaseDetails->isEmpty())
                <div class="px-4 py-4 text-center text-gray-500 text-sm">{{ __('messages.no_entries') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($purchaseDetails as $entry)
                        <li class="px-4 py-2 flex items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <span class="text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-3 h-3" /> {{ \App\Support\DateFormats::dateTime($entry->entry_date) }}</span>
                                {{ $entry->item->name ?? '—' }}
                                <span class="text-gray-500">× {{ \App\Support\ItemUnits::formatQuantity((float) $entry->quantity) }}</span>
                            </div>
                            <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) $entry->total) }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- 5. Expense report --}}
    <h2 class="section-title"><x-icon name="currency-dollar" class="w-5 h-5 text-rose-600" /> {{ __('messages.expense_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="currency-dollar" class="w-4 h-4 text-rose-600" /> {{ __('messages.total_expense') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['expense']) }}</span>
        </div>
        @if($expensesByHead->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($expensesByHead as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div>{{ $row['name'] }}</div>
                        <div class="font-bold shrink-0">{{ \App\Support\Money::format($row['total']) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-gray-100">
            <div class="px-4 py-2 bg-gray-50 text-xs font-bold text-gray-500 flex items-center gap-1.5"><x-icon name="document-text" class="w-3.5 h-3.5" /> {{ __('messages.entry_details') }}</div>
            @if($expenseDetails->isEmpty())
                <div class="px-4 py-4 text-center text-gray-500 text-sm">{{ __('messages.no_entries') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($expenseDetails as $entry)
                        <li class="px-4 py-2 flex items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <span class="text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-3 h-3" /> {{ \App\Support\DateFormats::dateTime($entry->entry_date) }}</span>
                                {{ $entry->expenseHead->name ?? '—' }}
                            </div>
                            <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) $entry->amount) }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- 6. Partner & commission report --}}
    <h2 class="section-title"><x-icon name="receipt-percent" class="w-5 h-5 text-violet-600" /> {{ __('messages.partner_commission_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        @if($partners->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_partners') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($partners as $partner)
                    <li class="px-4 py-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-bold flex items-center gap-1.5"><x-icon name="user-circle" class="w-4 h-4 text-gray-400" /> {{ $partner['name'] }}</div>
                            <div class="text-xs text-violet-600 font-bold flex items-center gap-1"><x-icon name="receipt-percent" class="w-3.5 h-3.5" /> {{ $partner['commission_rate'] }}%</div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div><span class="text-gray-500"><x-icon name="banknotes" class="w-3.5 h-3.5" /></span> {{ \App\Support\Money::format($partner['sales']) }}</div>
                            <div><span class="text-gray-500"><x-icon name="receipt-percent" class="w-3.5 h-3.5" /></span> {{ \App\Support\Money::format($partner['commission']) }}</div>
                            <div><span class="text-gray-500"><x-icon name="shopping-cart" class="w-3.5 h-3.5" /></span> {{ \App\Support\Money::format($partner['purchase']) }}</div>
                            <div><span class="text-gray-500"><x-icon name="currency-dollar" class="w-3.5 h-3.5" /></span> {{ \App\Support\Money::format($partner['expense']) }}</div>
                            <div><span class="text-gray-500"><x-icon name="credit-card" class="w-3.5 h-3.5" /></span> {{ \App\Support\Money::format($partner['payout']) }}</div>
                            <div class="font-bold {{ $partner['commission_due'] > 0 ? 'text-orange-600' : 'text-green-600' }} flex items-center gap-1">
                                <x-icon name="clock" class="w-3.5 h-3.5" /> {{ \App\Support\Money::format($partner['commission_due']) }}
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-4 py-2 bg-gray-50 text-xs text-gray-500 border-t border-gray-100 flex items-center gap-2 flex-wrap">
                <x-icon name="banknotes" class="w-3.5 h-3.5" /> {{ __('messages.total_sales') }} · <x-icon name="receipt-percent" class="w-3.5 h-3.5" /> {{ __('messages.commission_earned') }} · <x-icon name="shopping-cart" class="w-3.5 h-3.5" /> {{ __('messages.total_purchase') }} ·
                <x-icon name="currency-dollar" class="w-3.5 h-3.5" /> {{ __('messages.total_expense') }} · <x-icon name="credit-card" class="w-3.5 h-3.5" /> {{ __('messages.commission_paid') }} · <x-icon name="clock" class="w-3.5 h-3.5" /> {{ __('messages.commission_due_lifetime') }}
            </div>
        @endif
    </div>

    {{-- 7. Commission settlements report --}}
    <h2 class="section-title"><x-icon name="receipt-percent" class="w-5 h-5 text-violet-600" /> {{ __('messages.commission_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="receipt-percent" class="w-4 h-4 text-violet-600" /> {{ __('messages.total_commission') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['commission']) }}</span>
        </div>
        @if($settlements->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_commission') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($settlements as $settlement)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium flex items-center gap-1.5"><x-icon name="user-circle" class="w-4 h-4 text-gray-400" /> {{ $settlement->user->name }}</div>
                            <div class="text-gray-500 flex items-center gap-1.5 flex-wrap">
                                <x-icon name="calendar-days" class="w-3.5 h-3.5" /> {{ $settlement->period_start->format('d M') }} – {{ $settlement->period_end->format('d M Y') }}
                                · <x-icon name="arrow-trending-up" class="w-3.5 h-3.5" /> {{ \App\Support\Money::format((float) $settlement->business_profit) }}
                                · {{ $settlement->commission_rate }}%
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold">{{ \App\Support\Money::format((float) $settlement->amount) }}</div>
                            @if($settlement->status === 'paid')
                                <span class="text-xs text-green-700 flex items-center gap-1"><x-icon name="check" class="w-3.5 h-3.5" /> {{ __('messages.paid') }}</span>
                            @else
                                <span class="text-xs text-yellow-700 flex items-center gap-1"><x-icon name="clock" class="w-3.5 h-3.5" /> {{ __('messages.pending') }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 8. Owner withdrawal report --}}
    <h2 class="section-title"><x-icon name="building-library" class="w-5 h-5 text-amber-600" /> {{ __('messages.withdrawal_report') }}</h2>
    <div class="card mb-6 overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5"><x-icon name="building-library" class="w-4 h-4 text-amber-600" /> {{ __('messages.total_withdrawn') }}</span>
            <span class="font-bold">{{ \App\Support\Money::format($stats['withdrawal']) }}</span>
        </div>
        @if($withdrawals->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">{{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($withdrawals as $withdrawal)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $withdrawal->note ?? '—' }}</div>
                            <div class="text-gray-500 flex items-center gap-1.5"><x-icon name="calendar-days" class="w-3.5 h-3.5" /> {{ $withdrawal->withdrawn_at->format('d M Y') }}</div>
                        </div>
                        <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) $withdrawal->amount) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <style>
        @media print {
            header, nav, .print\:hidden { display: none !important; }
            body { background: white; }
        }
    </style>
@endsection
