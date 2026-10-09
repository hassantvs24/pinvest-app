@extends('layouts.app')

@section('title', __('messages.masters'))

@section('content')
    @php
        $sections = [
            'expense-heads' => ['title' => '💸 '.__('messages.expense_heads'), 'items' => $expenseHeads, 'has_price' => false],
            'purchase-items' => ['title' => '🛒 '.__('messages.purchase_items'), 'items' => $purchaseItems, 'has_price' => false],
            'sale-items' => ['title' => '💵 '.__('messages.sale_items'), 'items' => $saleItems, 'has_price' => true],
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach($sections as $group => $section)
            <div class="bg-white rounded-xl shadow p-4">
                <h2 class="font-bold text-lg mb-3">{{ $section['title'] }}</h2>

                {{-- Inline add form --}}
                <form method="POST" action="{{ route('owner.masters.store', ['group' => $group]) }}" class="mb-4">
                    @csrf
                    <input type="text" name="name" placeholder="{{ __('messages.item_name') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2">
                    @if($section['has_price'])
                        <input type="number" name="default_price" placeholder="{{ __('messages.default_price') }} (৳)" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2">
                    @endif
                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg py-3">
                        ➕ {{ __('messages.add_item') }}
                    </button>
                </form>

                {{-- Items list --}}
                <ul class="divide-y divide-gray-100">
                    @forelse($section['items'] as $item)
                        <li class="py-2 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-medium truncate {{ $item->is_active ? '' : 'line-through text-gray-400' }}">
                                    {{ $item->name }}
                                </div>
                                @if($section['has_price'])
                                    <div class="text-xs text-gray-500">৳{{ number_format((float) $item->default_price, 2) }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <form method="POST" action="{{ route('owner.masters.toggle', ['group' => $group, 'id' => $item->id]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" title="{{ $item->is_active ? __('messages.active') : __('messages.inactive') }}"
                                            class="text-xs px-2 py-1 rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                        {{ $item->is_active ? '✅' : '⏸️' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('owner.masters.destroy', ['group' => $group, 'id' => $item->id]) }}" class="js-confirm-delete">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 text-lg" title="{{ __('messages.delete') }}">🗑️</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-gray-500 text-sm">{{ __('messages.no_items') }}</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
@endsection
