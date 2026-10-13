@extends('layouts.app')

@section('title', __('messages.entries'))

@section('content')
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-gray-500 flex items-start gap-1.5">
            <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.owner_entries_hint') }}
        </p>
        <a href="{{ route('owner.stock-losses.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg px-3 py-1.5 shrink-0 transition">
            <x-icon name="arrow-trending-down" class="w-4 h-4" /> {{ __('messages.stock_losses') }}
        </a>
    </div>

    {{-- Filter form: type / partner / status --}}
    <form method="GET" action="{{ route('owner.entries.index') }}" class="card mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="label text-xs text-gray-500"><x-icon name="arrow-path" class="w-3.5 h-3.5" /> {{ __('messages.type') }}</label>
                <select name="type" onchange="this.form.submit()" class="input-sm">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>{{ __('messages.all') }}</option>
                    @foreach(['expenses', 'purchases', 'sales'] as $t)
                        <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ __('messages.'.$t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs text-gray-500"><x-icon name="user-group" class="w-3.5 h-3.5" /> {{ __('messages.partner') }}</label>
                <select name="partner_id" onchange="this.form.submit()" class="input-sm">
                    <option value="0">{{ __('messages.all') }}</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" {{ $partnerId === $partner->id ? 'selected' : '' }}>{{ $partner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs text-gray-500"><x-icon name="tag" class="w-3.5 h-3.5" /> {{ __('messages.status') }}</label>
                <select name="status" onchange="this.form.submit()" class="input-sm">
                    @foreach(['all', 'pending', 'confirmed', 'rejected'] as $s)
                        <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ __('messages.'.$s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- Entry cards --}}
    <div class="space-y-3">
        @forelse($entries as $entry)
            {{-- Mixed-type lists resolve each card's own type/config. --}}
            @php
                $entryType = $entry instanceof \App\Models\Expense ? 'expenses' : ($entry instanceof \App\Models\Sale ? 'sales' : 'purchases');
                $entryConfig = \App\Support\EntryTypes::config($entryType);
                $editItems = $itemsByType[$entryType];
                $typeIcon = $entryType === 'expenses' ? 'currency-dollar' : ($entryType === 'purchases' ? 'shopping-cart' : 'banknotes');
            @endphp
            <div class="entry-card card">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        @if($type === 'all')
                            <span class="badge badge-inactive mb-1">
                                <x-icon :name="$typeIcon" class="w-3.5 h-3.5" /> {{ __('messages.'.$entryConfig['label']) }}
                            </span>
                        @endif
                        <div class="font-bold">{{ $entry->{$entryConfig['relations'][1]}->name ?? '—' }}</div>
                        <div class="text-sm text-gray-500 inline-flex items-center gap-1.5"><x-avatar :user="$entry->user" size="22" /> {{ $entry->user->name }}</div>
                        @if($entryType !== 'expenses')
                            <div class="text-sm text-gray-500">
                                {{ \App\Support\ItemUnits::formatQuantity((float) $entry->quantity) }} {{ \App\Support\ItemUnits::label($entry->{$entryConfig['relations'][1]}->unit ?? null) }} × {{ \App\Support\Money::format((float) $entry->unit_price) }}
                            </div>
                        @endif
                        @if($entry->note)
                            <div class="text-sm text-gray-500 flex items-center gap-1.5">
                                <x-icon name="document-text" class="w-4 h-4" /> {{ $entry->note }}
                            </div>
                        @endif
                        @if($entryType === 'expenses' && $entry->item)
                            <div class="text-sm text-gray-500 flex items-center gap-1.5">
                                <x-icon name="cube" class="w-4 h-4" /> {{ $entry->item->name }}
                            </div>
                        @endif
                        <div class="text-sm text-gray-500 flex items-center gap-1.5">
                            <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($entry->entry_date) }}
                            <x-icon name="clock" class="w-4 h-4" /> {{ $entry->created_at->format(\App\Support\DateFormats::TIME) }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold">
                            {{ \App\Support\Money::format((float) ($entryType === 'expenses' ? $entry->amount : $entry->total)) }}
                        </div>
                        @php $st = $entry->status->value; @endphp
                        <span class="badge mt-1 {{ $st === 'pending' ? 'badge-pending' : ($st === 'confirmed' ? 'badge-confirmed' : 'badge-rejected') }}">
                            <x-icon :name="$st === 'pending' ? 'clock' : ($st === 'confirmed' ? 'check' : 'x-mark')" class="w-3.5 h-3.5" /> {{ __('messages.'.$st) }}
                        </span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap gap-2 mt-3">
                    @if($st === 'pending')
                        @php
                            // Warn inside the approval dialog when confirming
                            // this entry would overdraw the cash in hand.
                            $approveWarning = null;
                            if (in_array($entryType, ['purchases', 'expenses'], true)) {
                                $entryCost = $entryType === 'purchases' ? (float) $entry->total : (float) $entry->amount;
                                $projected = $cashInHand - $entryCost;
                                if ($projected < 0) {
                                    $approveWarning = __('messages.cash_overdraw_dialog', ['short' => number_format($projected, 2)]);
                                }
                            }
                        @endphp
                        <form method="POST" action="{{ route('owner.entries.confirm', ['type' => $entryType, 'entry' => $entry->id]) }}"
                              class="js-confirm-approve"@if($approveWarning) data-warning="{{ $approveWarning }}"@endif>
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-icon bg-green-600 text-white shadow-md shadow-green-600/25 hover:bg-green-700" title="{{ __('messages.confirm') }}">
                                <x-icon name="check" class="w-5 h-5" />
                            </button>
                        </form>
                        <form method="POST" action="{{ route('owner.entries.reject', ['type' => $entryType, 'entry' => $entry->id]) }}" class="js-confirm-reject">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-icon bg-red-500 text-white shadow-md shadow-red-500/25 hover:bg-red-600" title="{{ __('messages.reject') }}">
                                <x-icon name="x-mark" class="w-5 h-5" />
                            </button>
                        </form>
                    @endif
                    <button type="button" class="js-edit-toggle btn-icon bg-gray-100 text-gray-600 ring-1 ring-gray-200 hover:bg-gray-200" title="{{ __('messages.edit') }}">
                        <x-icon name="pencil" class="w-5 h-5" />
                    </button>
                    <form method="POST" action="{{ route('owner.entries.destroy', ['type' => $entryType, 'entry' => $entry->id]) }}" class="js-confirm-delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-icon bg-red-100 text-red-700 ring-1 ring-red-200 hover:bg-red-200" title="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-5 h-5" />
                        </button>
                    </form>
                </div>

                {{-- Inline edit form (hidden until Edit is clicked) --}}
                <form method="POST" action="{{ route('owner.entries.update', ['type' => $entryType, 'entry' => $entry->id]) }}"
                      class="js-edit-form js-confirm-update hidden mt-3 pt-3 border-t border-gray-200 space-y-3">
                    @csrf @method('PATCH')

                    <div>
                        <label class="label text-xs text-gray-500">{{ __('messages.'.($entryType === 'expenses' ? 'expense_head' : 'item')) }}</label>
                        <select name="head_id" class="input-sm">
                            @foreach($editItems as $item)
                                <option value="{{ $item->id }}"
                                    {{ $entry->{$entryConfig['item_field']} === $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($entryType === 'expenses')
                        <div>
                            <label class="label text-xs text-gray-500"><x-icon name="cube" class="w-3.5 h-3.5" /> {{ __('messages.related_product') }} ({{ __('messages.optional') }})</label>
                            <select name="item_id" class="input-sm">
                                <option value="">{{ __('messages.shared_across_products') }}</option>
                                @foreach($allItems as $product)
                                    <option value="{{ $product->id }}" {{ $entry->item_id === $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label text-xs text-gray-500">{{ __('messages.amount') }}</label>
                            <input type="number" name="amount" step="0.01" min="0" value="{{ $entry->amount }}" required class="input-sm">
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label text-xs text-gray-500">{{ __('messages.quantity') }}</label>
                                <input type="number" name="quantity" min="0.001" step="any" value="{{ (float) $entry->quantity }}" required class="input-sm">
                            </div>
                            <div>
                                <label class="label text-xs text-gray-500">{{ __('messages.total_amount') }}</label>
                                <input type="number" name="total" step="0.01" min="0.01" value="{{ $entry->total }}" required class="input-sm">
                            </div>
                        </div>
                        <div>
                            <label class="label text-xs text-gray-500">{{ __('messages.unit_price_auto') }}</label>
                            <input type="text" class="js-unit-price-preview input-sm input-readonly" readonly>
                        </div>
                    @endif

                    <div>
                        <label class="label text-xs text-gray-500">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ $entry->entry_date->format('Y-m-d') }}" required class="input-sm">
                    </div>

                    <div>
                        <label class="label text-xs text-gray-500">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                        <textarea name="note" rows="2" class="input-sm">{{ $entry->note }}</textarea>
                    </div>

                    <button type="submit" class="btn-primary w-full">
                        <x-icon name="check" class="w-4 h-4" /> {{ __('messages.save') }}
                    </button>
                </form>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="clipboard-document-list" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>

    <script>
        $(function () {
            // Live unit-price preview for the purchase/sale inline edit forms.
            function recalcEditUnitPrice($form) {
                var qty = parseFloat($form.find('[name="quantity"]').val());
                var total = parseFloat($form.find('[name="total"]').val());
                var $preview = $form.find('.js-unit-price-preview');

                if (!isNaN(qty) && qty > 0 && !isNaN(total)) {
                    $preview.val((total / qty).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                } else {
                    $preview.val('');
                }
            }

            $(document).on('input', '.js-edit-form [name="quantity"], .js-edit-form [name="total"]', function () {
                recalcEditUnitPrice($(this).closest('.js-edit-form'));
            });

            $('.js-edit-form').each(function () {
                recalcEditUnitPrice($(this));
            });
        });
    </script>
@endsection
