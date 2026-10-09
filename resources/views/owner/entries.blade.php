@extends('layouts.app')

@section('title', __('messages.entries'))

@section('content')
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-gray-500">💡 {{ __('messages.owner_entries_hint') }}</p>
        <a href="{{ route('owner.stock-losses.index') }}"
           class="text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg px-3 py-1.5 shrink-0">
            📉 {{ __('messages.stock_losses') }}
        </a>
    </div>

    {{-- Filter form: type / partner / status --}}
    <form method="GET" action="{{ route('owner.entries.index') }}" class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">🔀 {{ __('messages.type') }}</label>
                <select name="type" onchange="this.form.submit()" class="w-full border border-gray-300 rounded-lg px-3 py-3 bg-white">
                    @foreach(['expenses', 'purchases', 'sales'] as $t)
                        <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ __('messages.'.$t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">👥 {{ __('messages.partner') }}</label>
                <select name="partner_id" onchange="this.form.submit()" class="w-full border border-gray-300 rounded-lg px-3 py-3 bg-white">
                    <option value="0">{{ __('messages.all') }}</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" {{ $partnerId === $partner->id ? 'selected' : '' }}>{{ $partner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">🏷️ {{ __('messages.status') }}</label>
                <select name="status" onchange="this.form.submit()" class="w-full border border-gray-300 rounded-lg px-3 py-3 bg-white">
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
            <div class="entry-card bg-white rounded-xl shadow p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">{{ $entry->{$config['relations'][1]}->name ?? '—' }}</div>
                        <div class="text-sm text-gray-500 inline-flex items-center gap-1.5"><x-avatar :user="$entry->user" size="22" /> {{ $entry->user->name }}</div>
                        @if($type !== 'expenses')
                            <div class="text-sm text-gray-500">
                                {{ $entry->quantity }} {{ \App\Support\ItemUnits::label($entry->{$config['relations'][1]}->unit ?? null) }} × ৳{{ number_format((float) $entry->unit_price, 2) }}
                            </div>
                        @endif
                        @if($entry->note)
                            <div class="text-sm text-gray-500">📝 {{ $entry->note }}</div>
                        @endif
                        @if($type === 'expenses' && $entry->item)
                            <div class="text-sm text-gray-500">📦 {{ $entry->item->name }}</div>
                        @endif
                        <div class="text-sm text-gray-500">
                            📅 {{ $entry->entry_date->format(\App\Support\DateFormats::DATE) }}
                            🕐 {{ $entry->created_at->format(\App\Support\DateFormats::TIME) }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold">
                            ৳{{ number_format((float) ($type === 'expenses' ? $entry->amount : $entry->total), 2) }}
                        </div>
                        @php $st = $entry->status->value; @endphp
                        <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full
                            {{ $st === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($st === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                            {{ $st === 'pending' ? '⏳' : ($st === 'confirmed' ? '✅' : '❌') }} {{ __('messages.'.$st) }}
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
                            if (in_array($type, ['purchases', 'expenses'], true)) {
                                $entryCost = $type === 'purchases' ? (float) $entry->total : (float) $entry->amount;
                                $projected = $cashInHand - $entryCost;
                                if ($projected < 0) {
                                    $approveWarning = __('messages.cash_overdraw_dialog', ['short' => number_format($projected, 2)]);
                                }
                            }
                        @endphp
                        <form method="POST" action="{{ route('owner.entries.confirm', ['type' => $type, 'entry' => $entry->id]) }}"
                              class="js-confirm-approve"@if($approveWarning) data-warning="{{ $approveWarning }}"@endif>
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg px-3 py-2">✔️ {{ __('messages.confirm') }}</button>
                        </form>
                        <form method="POST" action="{{ route('owner.entries.reject', ['type' => $type, 'entry' => $entry->id]) }}" class="js-confirm-reject">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold rounded-lg px-3 py-2">✖️ {{ __('messages.reject') }}</button>
                        </form>
                    @endif
                    <button type="button" class="js-edit-toggle bg-gray-200 hover:bg-gray-300 text-sm font-bold rounded-lg px-3 py-2">✏️ {{ __('messages.edit') }}</button>
                    <form method="POST" action="{{ route('owner.entries.destroy', ['type' => $type, 'entry' => $entry->id]) }}" class="js-confirm-delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="bg-red-100 text-red-700 hover:bg-red-200 text-sm font-bold rounded-lg px-3 py-2">🗑️ {{ __('messages.delete') }}</button>
                    </form>
                </div>

                {{-- Inline edit form (hidden until Edit is clicked) --}}
                <form method="POST" action="{{ route('owner.entries.update', ['type' => $type, 'entry' => $entry->id]) }}"
                      class="js-edit-form js-confirm-update hidden mt-3 pt-3 border-t border-gray-200 space-y-3">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.'.($type === 'expenses' ? 'expense_head' : 'item')) }}</label>
                        <select name="head_id" class="w-full border border-gray-300 rounded-lg px-3 py-3 bg-white">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}"
                                    {{ $entry->{$config['item_field']} === $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($type === 'expenses')
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">📦 {{ __('messages.related_product') }} ({{ __('messages.optional') }})</label>
                            <select name="item_id" class="w-full border border-gray-300 rounded-lg px-3 py-3 bg-white">
                                <option value="">{{ __('messages.shared_across_products') }}</option>
                                @foreach($allItems as $product)
                                    <option value="{{ $product->id }}" {{ $entry->item_id === $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.amount') }}</label>
                            <input type="number" name="amount" step="0.01" min="0" value="{{ $entry->amount }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-3">
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.quantity') }}</label>
                                <input type="number" name="quantity" min="1" step="1" value="{{ $entry->quantity }}" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-3">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.unit_price') }}</label>
                                <input type="number" name="unit_price" step="0.01" min="0" value="{{ $entry->unit_price }}" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-3">
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ $entry->entry_date->format('Y-m-d') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-3">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                        <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-3">{{ $entry->note }}</textarea>
                    </div>

                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg py-3">
                        💾 {{ __('messages.save') }}
                    </button>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                📋 {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
