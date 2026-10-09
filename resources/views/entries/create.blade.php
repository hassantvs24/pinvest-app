@extends('layouts.app')

@section('title', __('messages.add_'.rtrim($type, 's')))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('entries.index', ['type' => $type]) }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h1 class="text-xl font-bold mb-4">{{ $config['icon'] }} {{ __('messages.add_'.rtrim($type, 's')) }}</h1>

            <form method="POST" action="{{ route('entries.store', ['type' => $type]) }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Item / head dropdown (master data, active only) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">
                        {{ __('messages.'.($type === 'expenses' ? 'expense_head' : ($type === 'purchases' ? 'purchase_item' : 'sale_item'))) }}
                    </label>
                    <select id="head_id" name="head_id" required
                            {{ $type === 'sales' ? 'data-autofill-price="1"' : '' }}
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">{{ __('messages.select_option') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-price="{{ $type === 'sales' ? $item->default_price : '' }}"
                                    {{ old('head_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}{{ $type === 'sales' ? ' (৳'.number_format((float) $item->default_price, 2).')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Expense: single amount --}}
                @if($type === 'expenses')
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('messages.amount') }}</label>
                        <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                @endif

                {{-- Purchase / Sale: quantity + unit price + live total --}}
                @if($config['has_quantity'])
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('messages.quantity') }}</label>
                            <input type="number" id="quantity" name="quantity" value="{{ old('quantity', 1) }}" min="1" step="1" required
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('messages.unit_price') }}</label>
                            <input type="number" id="unit_price" name="unit_price" value="{{ old('unit_price') }}" step="0.01" min="0" required
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('messages.total') }}</label>
                        <input type="text" id="total_display" readonly
                               class="w-full border border-gray-200 rounded-lg px-4 py-3 text-lg bg-gray-100 font-bold">
                    </div>
                @endif

                {{-- Sale: commission preview (server still recalculates on submit) --}}
                @if($type === 'sales')
                    <input type="hidden" id="commission_rate" value="{{ $commissionRate }}">
                    <div class="bg-violet-50 border border-violet-200 text-violet-800 rounded-lg px-4 py-3 text-sm">
                        🤝 {{ __('messages.your_commission') }} {{ __('messages.approx_label') }} ({{ $commissionRate }}%) =
                        ৳<span id="commission_preview">0.00</span>
                    </div>
                @endif

                {{-- Date (defaults to today) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.date') }}</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                {{-- Optional note --}}
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('note') }}</textarea>
                </div>

                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-xl py-3">
                    📩 {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>
    </div>
@endsection
