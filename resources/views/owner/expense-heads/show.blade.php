@extends('layouts.app')

@section('title', $head->name.' — '.__('messages.expense_heads_page'))

@section('content')
    <a href="{{ route('owner.expense-heads.index') }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

    {{-- Head summary --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4 flex items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold">{{ $head->name }}</h1>
            <span class="text-xs px-1.5 py-0.5 rounded {{ $head->cost_type->value === 'product' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                {{ $head->cost_type->value === 'product' ? __('messages.cost_type_product_short') : __('messages.cost_type_general_short') }}
            </span>
            <p class="text-xs text-gray-500 mt-1">
                {{ $count }} {{ __('messages.entry_count') }}
                ・{{ __('messages.avg_per_entry') }} ৳{{ $count > 0 ? number_format($total / $count, 2) : '0.00' }}
            </p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold">৳{{ number_format($total, 2) }}</div>
            <div class="text-xs text-gray-500">{{ __('messages.expense_total') }}</div>
        </div>
    </div>

    {{-- Pending expenses: not counted in the totals yet --}}
    @if($pending->isNotEmpty())
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-xl px-4 py-3 mb-4 text-sm">
            <div class="font-bold mb-1">⏳ {{ __('messages.pending_expenses') }}</div>
            <ul class="space-y-0.5">
                @foreach($pending as $expense)
                    <li>
                        📅 {{ $expense->entry_date->format(\App\Support\DateFormats::DATE) }}
                        − ৳{{ number_format((float) $expense->amount, 2) }}
                        @if($expense->user)・{{ $expense->user->name }}@endif
                        @if($expense->note) <span class="text-xs">({{ $expense->note }})</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Ledger entries --}}
    <h2 class="font-bold text-lg mb-2">📒 {{ __('messages.expense_heads_page') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        @if($rows->isEmpty())
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_entries') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $expense)
                    <li class="px-4 py-2 text-sm flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="text-gray-500">📅 {{ $expense->entry_date->format(\App\Support\DateFormats::DATE) }}</span>
                            @if($expense->user)<span class="text-xs text-gray-500">{{ $expense->user->name }}</span>@endif
                            @if($expense->item)
                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">📦 {{ $expense->item->name }}</span>
                            @endif
                            @if($expense->note)<span class="text-xs text-gray-500">{{ $expense->note }}</span>@endif
                        </div>
                        <div class="font-bold shrink-0 text-red-600">−৳{{ number_format((float) $expense->amount, 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($rows->hasPages())
        <div class="mb-6">{{ $rows->onEachSide(1)->links() }}</div>
    @endif
@endsection
