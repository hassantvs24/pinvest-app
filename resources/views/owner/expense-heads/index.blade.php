@extends('layouts.app')

@section('title', __('messages.expense_heads_page'))

@section('content')
    <h1 class="text-xl font-bold mb-1">💸 {{ __('messages.expense_heads_page') }}</h1>
    <p class="text-xs text-gray-500 mb-4">{{ __('messages.expense_heads_hint') }}</p>

    {{-- Grand totals --}}
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs text-gray-500">{{ __('messages.expense_total') }}</div>
            <div class="text-lg font-bold">৳{{ number_format($confirmedGrand, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs text-gray-500">⏳ {{ __('messages.expense_pending_total') }}</div>
            <div class="text-lg font-bold text-yellow-700">৳{{ number_format($pendingGrand, 2) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        @if($rows->isEmpty())
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_entries') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $i => $row)
                    <li>
                        <a href="{{ route('owner.expense-heads.show', $row['head']) }}" class="px-4 py-2 flex items-center justify-between gap-2 text-sm hover:bg-emerald-50">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $row['head']->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $row['head']->cost_type->value === 'product' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $row['head']->cost_type->value === 'product' ? __('messages.cost_type_product_short') : __('messages.cost_type_general_short') }}
                                </span>
                                @if($i === 0 && $row['confirmed_total'] > 0)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-200 text-amber-800 font-bold">🥇 {{ __('messages.top_expense_head') }}</span>
                                @endif
                                <div class="text-xs text-gray-500">
                                    {{ $row['confirmed_count'] }} {{ __('messages.entry_count') }}
                                    ・{{ __('messages.expense_share') }} {{ number_format($row['share'], 1) }}%
                                    @if($row['pending_count'] > 0)
                                        ・⏳ {{ $row['pending_count'] }} (৳{{ number_format($row['pending_total'], 2) }})
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0 font-bold">৳{{ number_format($row['confirmed_total'], 2) }}</div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
