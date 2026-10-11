@extends('layouts.app')

@section('title', __('messages.add_'.rtrim($type, 's')))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('entries.index', ['type' => $type]) }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 mb-3">
            <x-icon name="arrow-left" class="w-4 h-4" /> {{ __('messages.back') }}
        </a>

        <div class="card p-5">
            <h1 class="text-xl font-bold mb-4 flex items-center gap-2">
                @php $tIcon = $type === 'expenses' ? 'currency-dollar' : ($type === 'purchases' ? 'shopping-cart' : 'banknotes'); @endphp
                <x-icon :name="$tIcon" class="w-6 h-6 text-emerald-600" /> {{ __('messages.add_'.rtrim($type, 's')) }}
            </h1>
            <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.entry_hint_'.$type) }}
            </p>
            @if($type === 'sales')
                <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mb-3 flex items-start gap-1.5">
                    <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.warn_sale_unlinked') }}
                </p>
            @endif

            <form method="POST" action="{{ route('entries.store', ['type' => $type]) }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Item / head dropdown (master data, active only) --}}
                <div>
                    <label class="label">
                        {{ __('messages.'.($type === 'expenses' ? 'expense_head' : 'item')) }}
                    </label>
                    <select id="head_id" name="head_id" required class="input">
                        <option value="">{{ __('messages.select_option') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-price="{{ $type === 'sales' ? $item->default_price : '' }}"
                                    data-unit="{{ $config['has_unit'] ? \App\Support\ItemUnits::label($item->unit) : '' }}"
                                    data-available="{{ $type === 'sales' ? ($available[$item->id] ?? 0) : '' }}"
                                    data-cost-type="{{ $type === 'expenses' ? ($item->cost_type?->value ?? '') : '' }}"
                                    {{ old('head_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}{{ $type === 'sales' ? ' ('.\App\Support\Money::format((float) $item->default_price).')' : '' }}{{ $config['has_unit'] ? ' ('.\App\Support\ItemUnits::label($item->unit).')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($type === 'sales')
                    <p id="stock_hint" class="text-xs text-gray-600 -mt-2"></p>
                    @if($items->isEmpty())
                        <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 -mt-2 flex items-start gap-1.5">
                            <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.no_sellable_items') }}
                        </p>
                    @endif
                @endif

                {{-- Expense: optional product the cost belongs to (product-type heads only) --}}
                @if($type === 'expenses')
                    <div id="product_item_wrap" class="hidden">
                        <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mb-2 flex items-start gap-1.5">
                            <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.warn_expense_double_count') }}
                        </p>
                        <label class="label"><x-icon name="cube" class="w-4 h-4" /> {{ __('messages.related_product') }} ({{ __('messages.optional') }})</label>
                        <select name="item_id" class="input">
                            <option value="">{{ __('messages.shared_across_products') }}</option>
                            @foreach(($allItems ?? []) as $product)
                                <option value="{{ $product->id }}" {{ old('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.product_cost_hint') }}</p>
                    </div>
                @endif

                {{-- Expense: single amount --}}
                @if($type === 'expenses')
                    <div>
                        <label class="label">{{ __('messages.amount') }}</label>
                        <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" required class="input">
                    </div>
                @endif

                {{-- Purchase / Sale: quantity + unit price + live total --}}
                @if($config['has_quantity'])
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">{{ __('messages.quantity') }} <span id="unit_label" class="text-emerald-700 font-bold"></span></label>
                            <input type="number" id="quantity" name="quantity" value="{{ old('quantity', 1) }}" min="0.001" step="any" required class="input">
                        </div>
                        <div>
                            <label class="label">{{ __('messages.unit_price') }}</label>
                            <input type="number" id="unit_price" name="unit_price" value="{{ old('unit_price') }}" step="0.01" min="0" required class="input">
                        </div>
                    </div>

                    <div>
                        <label class="label">{{ __('messages.total') }}</label>
                        <input type="text" id="total_display" readonly class="input input-readonly">
                    </div>
                @endif

                {{-- Date: the moment is captured automatically (date + time of entry); the owner may backdate (day only) --}}
                @if(auth()->user()->isOwner())
                    <div>
                        <label class="label">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required class="input">
                        <p class="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                            <x-icon name="clock" class="w-4 h-4" /> {{ __('messages.auto_time_hint') }}
                        </p>
                    </div>
                @else
                    <p class="text-xs text-gray-500 flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ __('messages.auto_today_hint') }}
                    </p>
                @endif

                {{-- Optional note --}}
                <div>
                    <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
                </div>

                @if(auth()->user()->isOwner() && in_array($type, ['purchases', 'expenses'], true))
                    <p class="text-xs text-gray-500 flex items-center gap-1.5">
                        <x-icon name="banknotes" class="w-4 h-4" /> {{ __('messages.current_cash') }}: {{ \App\Support\Money::format((float) $cashInHand) }}
                    </p>
                    <p id="cash_overdraw_hint" class="hidden text-xs bg-red-50 border border-red-300 text-red-700 rounded-lg px-3 py-2"></p>
                @endif

                <button type="submit" class="btn-primary w-full text-lg">
                    <x-icon name="check" class="w-5 h-5" /> {{ __('messages.submit_entry') }}
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
                    hint.text(text);
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
