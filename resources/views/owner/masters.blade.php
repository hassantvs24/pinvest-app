@extends('layouts.app')

@section('title', __('messages.masters'))

@section('content')
    @php
        $sections = [
            'expense-heads' => ['title' => '💸 '.__('messages.expense_heads'), 'items' => $expenseHeads, 'has_price' => false, 'has_unit' => false, 'has_link' => false, 'has_cost_type' => true, 'hint' => __('messages.masters_hint_expense_heads')],
            'purchase-items' => ['title' => '🛒 '.__('messages.purchase_items'), 'items' => $purchaseItems, 'has_price' => false, 'has_unit' => true, 'has_link' => false, 'has_cost_type' => false, 'hint' => __('messages.masters_hint_purchase_items')],
            'sale-items' => ['title' => '💵 '.__('messages.sale_items'), 'items' => $saleItems, 'has_price' => true, 'has_unit' => true, 'has_link' => true, 'has_cost_type' => false, 'hint' => __('messages.masters_hint_sale_items')],
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach($sections as $group => $section)
            <div class="bg-white rounded-xl shadow p-4">
                <h2 class="font-bold text-lg mb-1">{{ $section['title'] }}</h2>
                <p class="text-xs text-gray-500 mb-3">💡 {{ $section['hint'] }}</p>
                @if($section['has_link'])
                    <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mb-3">⚠️ {{ __('messages.warn_unit_locked') }}</p>
                @endif

                {{-- Inline add form --}}
                <form method="POST" action="{{ route('owner.masters.store', ['group' => $group]) }}" class="mb-4">
                    @csrf
                    <input type="text" name="name" placeholder="{{ __('messages.item_name') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2">
                    @if($section['has_price'])
                        <input type="number" name="default_price" placeholder="{{ __('messages.default_price') }} (৳)" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2">
                    @endif
                    @if($section['has_unit'])
                        <select name="unit" class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2 bg-white">
                            @foreach(\App\Support\ItemUnits::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($section['has_link'])
                        <select name="purchase_item_id" class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2 bg-white">
                            <option value="">{{ __('messages.linked_purchase_item') }} ({{ __('messages.optional') }})</option>
                            @foreach($purchaseItems as $purchaseItem)
                                <option value="{{ $purchaseItem->id }}">{{ $purchaseItem->name }} ({{ \App\Support\ItemUnits::label($purchaseItem->unit) }})</option>
                            @endforeach
                        </select>
                    @endif
                    @if($section['has_cost_type'])
                        <select name="cost_type" class="w-full border border-gray-300 rounded-lg px-3 py-3 mb-2 bg-white">
                            <option value="general">{{ __('messages.cost_type_general') }}</option>
                            <option value="product">{{ __('messages.cost_type_product') }}</option>
                        </select>
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
                                @if($section['has_unit'])
                                    <div class="text-xs text-emerald-700">⚖️ {{ \App\Support\ItemUnits::label($item->unit) }}</div>
                                @endif
                                @if($section['has_link'])
                                    <div class="text-xs {{ $item->purchaseItem ? 'text-gray-500' : 'text-yellow-600 font-medium' }}">
                                        🔗 {{ $item->purchaseItem ? $item->purchaseItem->name : __('messages.not_linked') }}
                                    </div>
                                @endif
                                @if($section['has_cost_type'])
                                    <div class="text-xs {{ $item->cost_type?->addsToStock() ? 'text-violet-600' : 'text-gray-500' }}">
                                        🏷️ {{ $item->cost_type?->addsToStock() ? __('messages.cost_type_product') : __('messages.cost_type_general') }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" class="js-item-edit-toggle text-gray-400 hover:text-gray-600" title="{{ __('messages.edit') }}">✏️</button>
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

                        {{-- Inline rename form (hidden until the pencil is clicked) --}}
                        <li class="js-item-edit-form hidden bg-gray-50 px-2 py-2 -mt-1">
                            <form method="POST" action="{{ route('owner.masters.update', ['group' => $group, 'id' => $item->id]) }}" class="space-y-2">
                                @csrf @method('PATCH')
                                <input type="text" name="name" maxlength="255" value="{{ $item->name }}" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @if($section['has_link'])
                                    <select name="purchase_item_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                        <option value="">{{ __('messages.no_link') }}</option>
                                        @foreach($purchaseItems as $purchaseItem)
                                            <option value="{{ $purchaseItem->id }}" {{ $item->purchase_item_id === $purchaseItem->id ? 'selected' : '' }}>
                                                {{ $purchaseItem->name }} ({{ \App\Support\ItemUnits::label($purchaseItem->unit) }})
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @if($section['has_cost_type'])
                                    <select name="cost_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                        <option value="general" {{ ! $item->cost_type?->addsToStock() ? 'selected' : '' }}>{{ __('messages.cost_type_general') }}</option>
                                        <option value="product" {{ $item->cost_type?->addsToStock() ? 'selected' : '' }}>{{ __('messages.cost_type_product') }}</option>
                                    </select>
                                @endif
                                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg px-3 py-2 text-sm">
                                    💾 {{ __('messages.save') }}
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="py-4 text-center text-gray-500 text-sm">{{ __('messages.no_items') }}</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
@endsection
