@extends('layouts.app')

@section('title', __('messages.add_'.rtrim($type, 's')))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('entries.index', ['type' => $type]) }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h1 class="text-xl font-bold mb-4">{{ $config['icon'] }} {{ __('messages.add_'.rtrim($type, 's')) }}</h1>
            <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.entry_hint_'.$type) }}</p>
            @if($type === 'sales')
                <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mb-3">⚠️ {{ __('messages.warn_sale_unlinked') }}</p>
            @endif

            <form method="POST" action="{{ route('entries.store', ['type' => $type]) }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Item / head dropdown (master data, active only) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">
                        {{ __('messages.'.($type === 'expenses' ? 'expense_head' : 'item')) }}
                    </label>
                    <select id="head_id" name="head_id" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">{{ __('messages.select_option') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-price="{{ $type === 'sales' ? $item->default_price : '' }}"
                                    data-unit="{{ $config['has_unit'] ? \App\Support\ItemUnits::label($item->unit) : '' }}"
                                    data-available="{{ $type === 'sales' ? ($available[$item->id] ?? 0) : '' }}"
                                    data-cost-type="{{ $type === 'expenses' ? ($item->cost_type?->value ?? '') : '' }}"
                                    {{ old('head_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}{{ $type === 'sales' ? ' (৳'.number_format((float) $item->default_price, 2).')' : '' }}{{ $config['has_unit'] ? ' ('.\App\Support\ItemUnits::label($item->unit).')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($type === 'sales')
                    <p id="stock_hint" class="text-xs text-gray-600 -mt-2"></p>
                    @if($items->isEmpty())
                        <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 -mt-2">⚠️ {{ __('messages.no_sellable_items') }}</p>
                    @endif
                @endif

                {{-- Expense: optional product the cost belongs to (product-type heads only) --}}
                @if($type === 'expenses')
                    <div id="product_item_wrap" class="hidden">
                        <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mb-2">⚠️ {{ __('messages.warn_expense_double_count') }}</p>
                        <label class="block text-sm font-medium mb-1">📦 {{ __('messages.related_product') }} ({{ __('messages.optional') }})</label>
                        <select name="item_id" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">{{ __('messages.shared_across_products') }}</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.product_cost_hint') }}</p>
                    </div>
                @endif

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
                            <label class="block text-sm font-medium mb-1">{{ __('messages.quantity') }} <span id="unit_label" class="text-emerald-700 font-bold"></span></label>
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

                {{-- Date: partners always report today (server forces it); owner may backdate --}}
                @if(auth()->user()->isOwner())
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                @else
                    <input type="hidden" name="entry_date" value="{{ $today }}">
                    <p class="text-xs text-gray-500">📅 {{ __('messages.auto_today_hint') }}</p>
                @endif

                {{-- Optional note --}}
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('note') }}</textarea>
                </div>

                @if(auth()->user()->isOwner() && in_array($type, ['purchases', 'expenses'], true))
                    <p class="text-xs text-gray-500">💵 {{ __('messages.current_cash') }}: ৳{{ number_format((float) $cashInHand, 2) }}</p>
                    <p id="cash_overdraw_hint" class="hidden text-xs bg-red-50 border border-red-300 text-red-700 rounded-lg px-3 py-2"></p>
                @endif

                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-xl py-3">
                    📩 {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>
    </div>

    @if(auth()->user()->isOwner() && in_array($type, ['purchases', 'expenses'], true))
        <script>
            $(function () {
                // Warn the owner when an entry would overdraw the cash.
                var cash = {{ (float) $cashInHand }};
                function checkCashOverdraw() {
                    var total = 0;
                    @if($type === 'purchases')
                        total = (parseFloat($('#quantity').val()) || 0) * (parseFloat($('#unit_price').val()) || 0);
                    @else
                        total = parseFloat($('#amount').val()) || 0;
                    @endif
                    var short = cash - total;
                    var hint = $('#cash_overdraw_hint');
                    if (short < 0) {
                        var text = @json(__('messages.cash_overdraw_warning'))
                            .replace(':cash', cash.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                            .replace(':short', Math.abs(short).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        hint.removeClass('hidden').text(text);
                    } else {
                        hint.addClass('hidden').text('');
                    }
                }
                $(document).on('input', '#quantity, #unit_price, #amount', checkCashOverdraw);
                checkCashOverdraw();
            });
        </script>
    @endif

    @if($type === 'sales')
        <script>
            $(function () {
                // Live hint: how much of the selected item may still be sold.
                function updateStockHint() {
                    var selected = $('#head_id option:selected');
                    var available = parseFloat(selected.data('available'));
                    var unit = selected.data('unit') || '';
                    var hint = $('#stock_hint');
                    if (isNaN(available)) {
                        hint.text('').removeClass('text-red-600 font-bold').addClass('text-gray-600');
                        return;
                    }
                    var text = @json(__('messages.available_stock_hint')).replace(':available', available).replace(':unit', unit);
                    hint.text('📦 ' + text);
                    hint.toggleClass('text-red-600 font-bold', available <= 0);
                }
                $(document).on('change', '#head_id', updateStockHint);
                updateStockHint();
            });
        </script>
    @endif

    @if($type === 'expenses')
        <script>
            $(function () {
                // Product item picker only makes sense for product-type heads.
                function toggleProductItem() {
                    var isProduct = $('#head_id option:selected').data('cost-type') === 'product';
                    $('#product_item_wrap').toggleClass('hidden', !isProduct);
                }
                $(document).on('change', '#head_id', toggleProductItem);
                toggleProductItem();
            });
        </script>
    @endif
@endsection
