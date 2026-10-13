@extends('layouts.app')

@section('title', __('messages.expense_heads_page'))

@section('content')
    <h1 class="page-title flex items-center gap-2">
        <x-icon name="currency-dollar" class="w-6 h-6 text-emerald-600" /> {{ __('messages.expense_heads_page') }}
    </h1>
    <p class="text-xs text-gray-500 mb-4">{{ __('messages.expense_heads_hint') }}</p>

    {{-- Grand totals --}}
    <div class="grid grid-cols-2 gap-3 mb-4">
        <x-stat-card :label="__('messages.expense_total')" :value="\App\Support\Money::format($confirmedGrand)" icon="currency-dollar" color="rose" />
        <x-stat-card :label="__('messages.expense_pending_total')" :value="\App\Support\Money::format($pendingGrand)" icon="clock" color="amber" valueClass="text-amber-700" />
    </div>

    <div class="card mb-6 overflow-hidden p-0">
        @if($rows->isEmpty())
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_entries') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $i => $row)
                    <li>
                        <a href="{{ route('owner.expense-heads.show', $row['head']) }}" class="px-4 py-2 flex items-center justify-between gap-2 text-sm hover:bg-emerald-50 transition">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $row['head']->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $row['head']->cost_type->value === 'product' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $row['head']->cost_type->value === 'product' ? __('messages.cost_type_product_short') : __('messages.cost_type_general_short') }}
                                </span>
                                @if($i === 0 && $row['confirmed_total'] > 0)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-200 text-amber-800 font-bold"><x-icon name="star" class="w-3 h-3" /> {{ __('messages.top_expense_head') }}</span>
                                @endif
                                <div class="text-xs text-gray-500">
                                    {{ $row['confirmed_count'] }} {{ __('messages.entry_count') }}
                                    ・{{ __('messages.expense_share') }} {{ number_format($row['share'], 1) }}%
                                    @if($row['pending_count'] > 0)
                                        ・<x-icon name="clock" class="w-3 h-3" /> {{ $row['pending_count'] }} ({{ \App\Support\Money::format($row['pending_total']) }})
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0 font-bold">{{ \App\Support\Money::format($row['confirmed_total']) }}</div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
