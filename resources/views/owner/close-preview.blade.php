@extends('layouts.app')

@section('title', __('messages.close_summary'))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('owner.commissions.index') }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h1 class="text-xl font-bold mb-1">🔒 {{ __('messages.close_summary') }}</h1>
            <p class="text-sm text-gray-500 mb-4">
                {{ $period->label ?: $period->opened_at->format('M Y') }} —
                📅 {{ $period->opened_at->format('d M Y') }} → {{ $closedAt->format('d M Y') }}
            </p>

            {{-- Blocking panel: unapproved entries must be resolved first --}}
            @if($pendingEntries->isNotEmpty())
                <div class="bg-yellow-50 border border-yellow-400 rounded-xl p-4 mb-4">
                    <div class="font-bold text-yellow-900 mb-1">🚫 {{ __('messages.unapproved_entries') }} ({{ $pendingEntries->count() }})</div>
                    <p class="text-sm text-yellow-800 mb-2">
                        {{ trans_choice(__('messages.close_blocked_pending'), $pendingEntries->count(), ['count' => $pendingEntries->count()]) }}
                    </p>
                    <ul class="divide-y divide-yellow-200 text-sm">
                        @foreach($pendingEntries as $entry)
                            <li class="py-2 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-medium">
                                        {{ $entry->type_icon }} {{ $entry->type_label }}
                                        · {{ $entry->type_item_name ?? $entry->{$entry->type_item_relation}->name ?? '—' }}
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <span class="inline-flex items-center gap-1.5"><x-avatar :user="$entry->user" size="20" /> {{ $entry->user->name }}</span>
                                        · 📅 {{ \App\Support\DateFormats::date($entry->entry_date) }}
                                        🕐 {{ $entry->created_at->format(\App\Support\DateFormats::TIME) }}
                                    </div>
                                </div>
                                <div class="font-bold shrink-0">{{ \App\Support\Money::format((float) ($entry->amount ?? $entry->total)) }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Period breakdown: sales / COGS / purchases / expenses / stock --}}
            <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 mb-4 text-sm">
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>💰 {{ __('messages.total_sales') }}</span>
                    <span class="font-bold text-emerald-700">{{ \App\Support\Money::format($stats['sales']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>🏷️ {{ __('messages.cost_of_goods_sold') }}</span>
                    <span class="font-bold text-indigo-700">−{{ \App\Support\Money::format($stats['cogs']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>🛒 {{ __('messages.total_purchase') }} <span class="text-xs text-gray-400">({{ __('messages.cash_reference') }})</span></span>
                    <span class="font-bold text-orange-700">{{ \App\Support\Money::format($stats['purchase']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>📦 {{ __('messages.product_expense') }}</span>
                    <span class="font-bold text-violet-700">{{ \App\Support\Money::format($stats['product_expense']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>🧾 {{ __('messages.general_expense') }}</span>
                    <span class="font-bold text-red-700">−{{ \App\Support\Money::format($stats['expense']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between">
                    <span>📉 {{ __('messages.stock_loss') }}</span>
                    <span class="font-bold text-red-700">−{{ \App\Support\Money::format($stats['stock_loss']) }}</span>
                </li>
                <li class="px-4 py-2 flex items-center justify-between bg-gray-50">
                    <span>🏪 {{ __('messages.stock_value') }}</span>
                    <span class="font-bold text-teal-700">{{ \App\Support\Money::format($stats['stock_value']) }}</span>
                </li>
            </ul>

            {{-- Data-quality warnings (unlinked items, negative stock, ...) --}}
            @if(count($stats['warnings']) > 0)
                <div class="bg-yellow-50 border border-yellow-400 rounded-xl p-4 mb-4">
                    <div class="font-bold text-yellow-900 mb-1">⚠️ {{ __('messages.attention') }}</div>
                    <ul class="list-disc list-inside text-sm text-yellow-800 space-y-1">
                        @foreach($stats['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Profit --}}
            <div class="rounded-xl p-4 mb-4 {{ $profit >= 0 ? 'bg-green-50 border border-green-300' : 'bg-red-50 border border-red-300' }}">
                <div class="text-sm text-gray-500">📈 {{ __('messages.net_profit') }}</div>
                <div class="text-3xl font-bold {{ $profit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    {{ \App\Support\Money::format($profit) }}
                </div>
            </div>

            @if($profit > 0 && count($rows) > 0)
                {{-- Per-partner expected commission --}}
                <h2 class="font-bold mb-2">🤝 {{ __('messages.expected_commission') }}</h2>
                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 mb-4">
                    @foreach($rows as $row)
                        <li class="px-4 py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-medium inline-flex items-center gap-1.5"><x-avatar :user="$row['user']" size="20" /> {{ $row['user']->name }}</span>
                                <span class="text-xs text-violet-600">🤝 {{ $row['rate'] }}%</span>
                            </div>
                            <div class="font-bold">{{ \App\Support\Money::format($row['amount']) }}</div>
                        </li>
                    @endforeach
                    <li class="px-4 py-2 flex items-center justify-between text-sm bg-violet-50 font-bold">
                        <div>🤝 {{ __('messages.total_commission') }}</div>
                        <div>{{ \App\Support\Money::format($totalCommission) }}</div>
                    </li>
                    <li class="px-4 py-2 flex items-center justify-between text-sm bg-emerald-50 font-bold">
                        <div>👔 {{ __('messages.owner_share') }}</div>
                        <div class="{{ $ownerShare >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ \App\Support\Money::format($ownerShare) }}</div>
                    </li>
                </ul>
            @elseif($profit <= 0)
                <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">
                    📉 {{ __('messages.loss_no_commission') }}
                </div>
            @endif

            {{-- Confirm (hidden while unapproved entries block the close) --}}
            @if($pendingEntries->isEmpty())
                <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mb-4">💡 {{ __('messages.close_preview_hint') }}</p>
                <form method="POST" action="{{ route('owner.commissions.close') }}" class="js-confirm-update">
                    @csrf
                    <input type="hidden" name="closed_at" value="{{ $closedAt->format('Y-m-d') }}">
                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl py-3">
                        ✅ {{ __('messages.confirm_close') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
