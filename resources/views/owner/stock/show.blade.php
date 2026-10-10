@extends('layouts.app')

@section('title', $item->name.' — '.__('messages.stock_ledger'))

@section('content')
    <a href="{{ route('owner.stock.index') }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

    {{-- Item summary --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4 flex items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold">{{ $item->name }}</h1>
            <p class="text-xs text-gray-500">{{ __('messages.avg_cost') }}: {{ \App\Support\Money::format($summary['avg_cost']) }}/{{ \App\Support\ItemUnits::label($item->unit) }}</p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold {{ $summary['base_quantity'] <= 0.00001 ? 'text-red-600' : 'text-emerald-700' }}">
                {{ rtrim(rtrim(number_format($summary['quantity'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}
            </div>
            <div class="text-xs text-gray-500">= {{ \App\Support\Money::format($summary['value']) }}</div>
        </div>
    </div>

    {{-- Ledger summary: what the whole history adds up to --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4">
        <h2 class="font-bold mb-2">📒 {{ __('messages.ledger_summary') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            <li class="py-1.5 flex items-center justify-between gap-2">
                <span>🛒 {{ __('messages.total_purchase') }}</span>
                <span class="text-right">
                    <span class="font-bold">{{ rtrim(rtrim(number_format($totals['purchase']['qty'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}</span>
                    <span class="text-xs text-gray-500">= {{ \App\Support\Money::format($totals['purchase']['amount']) }}</span>
                </span>
            </li>
            <li class="py-1.5 flex items-center justify-between gap-2">
                <span>💵 {{ __('messages.total_sales') }}</span>
                <span class="text-right">
                    <span class="font-bold">{{ rtrim(rtrim(number_format($totals['sale']['qty'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}</span>
                    <span class="text-xs text-gray-500">= {{ \App\Support\Money::format($totals['sale']['amount']) }}</span>
                </span>
            </li>
            <li class="py-1.5 flex items-center justify-between gap-2">
                <span>🏭 {{ __('messages.ledger_production_in') }}</span>
                <span class="text-right">
                    <span class="font-bold text-emerald-700">+{{ rtrim(rtrim(number_format($totals['production_in']['qty'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}</span>
                    <span class="text-xs text-gray-500">= {{ \App\Support\Money::format($totals['production_in']['amount']) }}</span>
                </span>
            </li>
            <li class="py-1.5 flex items-center justify-between gap-2">
                <span>🏭 {{ __('messages.ledger_production_out') }}</span>
                <span class="text-right">
                    <span class="font-bold text-red-600">−{{ rtrim(rtrim(number_format($totals['production_out']['qty'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}</span>
                    <span class="text-xs text-gray-500">= {{ \App\Support\Money::format($totals['production_out']['amount']) }}</span>
                </span>
            </li>
            <li class="py-1.5 flex items-center justify-between gap-2">
                <span>📉 {{ __('messages.stock_loss') }}</span>
                <span class="text-right">
                    <span class="font-bold text-red-600">−{{ rtrim(rtrim(number_format($totals['stock-loss']['qty'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}</span>
                    <span class="text-xs text-gray-500">= {{ \App\Support\Money::format($totals['stock-loss']['amount']) }}</span>
                </span>
            </li>
        </ul>
    </div>

    {{-- Pending sales/losses: reserve stock but do not move it yet --}}
    @if($pending !== [])
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-xl px-4 py-3 mb-4 text-sm">
            <div class="font-bold mb-1">⏳ {{ __('messages.pending_reservations') }}</div>
            <ul class="space-y-0.5">
                @foreach($pending as $sale)
                    <li>
                        📅 {{ \App\Support\DateFormats::date($sale['date']) }}
                        − {{ rtrim(rtrim(number_format($sale['quantity'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($item->unit) }}
                        @if($sale['note'])<span class="text-xs">({{ $sale['note'] }})</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Movement ledger --}}
    <h2 class="font-bold text-lg mb-2">📒 {{ __('messages.stock_ledger') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        @if($rows->isEmpty())
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_entries') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $row)
                    <li class="px-4 py-2 text-sm flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="text-gray-500">📅 {{ \App\Support\DateFormats::date($row['date']) }}</span>
                            @if($row['direction'] === 'in')
                                <span class="text-emerald-700 font-bold">+{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</span>
                                @if($row['kind'] === 'production')
                                    <a href="{{ route('owner.productions.index') }}" class="text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 underline">{{ __('messages.ledger_kind_production') }}</a>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">{{ __('messages.ledger_kind_'.$row['kind']) }}</span>
                                @endif
                            @else
                                <span class="text-red-600 font-bold">−{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</span>
                                @if($row['kind'] === 'production')
                                    <a href="{{ route('owner.productions.index') }}" class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-600 underline">{{ __('messages.ledger_kind_production') }}</a>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-600">{{ __('messages.ledger_kind_'.$row['kind']) }}</span>
                                @endif
                            @endif
                            @if($row['unit_price'] !== null)
                                <span class="text-xs text-gray-600">
                                    @if($row['kind'] === 'production' || $row['kind'] === 'stock-loss')💰 {{ __('messages.ledger_cost') }}@endif
                                    {{ \App\Support\Money::format($row['unit_price']) }}/{{ \App\Support\ItemUnits::label($item->unit) }}
                                    = <span class="font-bold">{{ \App\Support\Money::format($row['total']) }}</span>
                                </span>
                            @endif
                            @if($row['note'])<span class="text-xs text-gray-500">{{ $row['note'] }}</span>@endif
                        </div>
                        <div class="text-right shrink-0 font-bold {{ $row['balance'] < 0 ? 'text-red-600' : 'text-gray-700' }}">
                            {{ rtrim(rtrim(number_format($row['balance'], 2), '0'), '.') }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($rows->hasPages())
        <div class="mb-6">{{ $rows->onEachSide(1)->links() }}</div>
    @endif
@endsection
