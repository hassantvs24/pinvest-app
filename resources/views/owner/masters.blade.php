@extends('layouts.app')

@section('title', __('messages.masters'))

@section('content')
    @php
        $sections = [
            'items' => ['title' => __('messages.items'), 'icon' => 'cube', 'items' => $items, 'has_price' => true, 'has_unit' => true, 'has_cost_type' => false, 'hint' => __('messages.masters_hint_items')],
            'expense-heads' => ['title' => __('messages.expense_heads'), 'icon' => 'currency-dollar', 'items' => $expenseHeads, 'has_price' => false, 'has_unit' => false, 'has_cost_type' => true, 'hint' => __('messages.masters_hint_expense_heads')],
        ];
    @endphp

    <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.masters_intro_hint') }}
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($sections as $group => $section)
            <div class="card">
                <h2 class="font-bold text-lg mb-1 flex items-center gap-2">
                    <x-icon :name="$section['icon']" class="w-5 h-5 text-emerald-600" /> {{ $section['title'] }}
                </h2>
                <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                    <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ $section['hint'] }}
                </p>
                @if($group === 'items')
                    <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mb-3 flex items-start gap-1.5">
                        <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.warn_unit_locked') }}
                    </p>
                @endif

                {{-- Inline add form --}}
                <form method="POST" action="{{ route('owner.masters.store', ['group' => $group]) }}" class="mb-4">
                    @csrf
                    <input type="text" name="name" placeholder="{{ __('messages.item_name') }}" required class="input mb-2">
                    @if($section['has_price'])
                        <input type="number" name="default_price" placeholder="{{ __('messages.default_price') }} ({{ \App\Support\Money::SYMBOL }})" step="0.01" min="0" required class="input mb-2">
                    @endif
                    @if($section['has_unit'])
                        <select name="unit" class="input mb-2">
                            @foreach(\App\Support\ItemUnits::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($section['has_cost_type'])
                        <select name="cost_type" class="input mb-2">
                            <option value="general">{{ __('messages.cost_type_general') }}</option>
                            <option value="product">{{ __('messages.cost_type_product') }}</option>
                        </select>
                    @endif
                    <button type="submit" class="btn-primary w-full">
                        <x-icon name="plus" class="w-5 h-5" /> {{ __('messages.add_item') }}
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
                                    <div class="text-xs text-gray-500">{{ \App\Support\Money::format((float) $item->default_price) }}</div>
                                @endif
                                @if($section['has_unit'])
                                    <div class="text-xs text-emerald-700 flex items-center gap-1"><x-icon name="calculator" class="w-3.5 h-3.5" /> {{ \App\Support\ItemUnits::label($item->unit) }}</div>
                                @endif
                                @if($section['has_cost_type'])
                                    <div class="text-xs {{ $item->cost_type?->addsToStock() ? 'text-violet-600' : 'text-gray-500' }} flex items-center gap-1">
                                        <x-icon name="tag" class="w-3.5 h-3.5" /> {{ $item->cost_type?->addsToStock() ? __('messages.cost_type_product') : __('messages.cost_type_general') }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" class="js-item-edit-toggle btn-icon w-9 h-9 text-gray-400 hover:text-gray-600 hover:bg-gray-100" title="{{ __('messages.edit') }}">
                                    <x-icon name="pencil" class="w-5 h-5" />
                                </button>
                                <form method="POST" action="{{ route('owner.masters.toggle', ['group' => $group, 'id' => $item->id]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" title="{{ $item->is_active ? __('messages.active') : __('messages.inactive') }}"
                                            class="text-xs px-2 py-1 rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                        @if($item->is_active)
                                            <x-icon name="check" class="w-3.5 h-3.5" />
                                        @else
                                            <x-icon name="minus" class="w-3.5 h-3.5" />
                                        @endif
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('owner.masters.destroy', ['group' => $group, 'id' => $item->id]) }}" class="js-confirm-delete">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-icon w-9 h-9 text-red-500 hover:bg-red-50" title="{{ __('messages.delete') }}">
                                        <x-icon name="trash" class="w-5 h-5" />
                                    </button>
                                </form>
                            </div>
                        </li>

                        {{-- Inline rename form (hidden until the pencil is clicked) --}}
                        <li class="js-item-edit-form hidden bg-gray-50 px-2 py-2 -mt-1">
                            <form method="POST" action="{{ route('owner.masters.update', ['group' => $group, 'id' => $item->id]) }}" class="space-y-2">
                                @csrf @method('PATCH')
                                <input type="text" name="name" maxlength="255" value="{{ $item->name }}" required class="input-sm">
                                @if($section['has_price'])
                                    @php $priceLocked = $group === 'items' && $priceLockedIds->contains($item->id); @endphp
                                    <input type="number" name="default_price" step="0.01" min="0" value="{{ (float) $item->default_price }}"
                                           {{ $priceLocked ? 'readonly' : 'required' }}
                                           class="input-sm {{ $priceLocked ? 'bg-gray-100 text-gray-500' : '' }}">
                                    @if($priceLocked)
                                        <p class="text-xs text-red-600 flex items-center gap-1.5"><x-icon name="lock-closed" class="w-3.5 h-3.5" /> {{ __('messages.price_locked_in_production') }}</p>
                                    @else
                                        <p class="text-xs text-gray-500 flex items-center gap-1.5"><x-icon name="information-circle" class="w-3.5 h-3.5" /> {{ __('messages.price_edit_hint') }}</p>
                                    @endif
                                @endif
                                @if($section['has_cost_type'])
                                    <select name="cost_type" class="input-sm">
                                        <option value="general" {{ ! $item->cost_type?->addsToStock() ? 'selected' : '' }}>{{ __('messages.cost_type_general') }}</option>
                                        <option value="product" {{ $item->cost_type?->addsToStock() ? 'selected' : '' }}>{{ __('messages.cost_type_product') }}</option>
                                    </select>
                                @endif
                                <button type="submit" class="btn-primary w-full text-sm">
                                    <x-icon name="check" class="w-4 h-4" /> {{ __('messages.save') }}
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
