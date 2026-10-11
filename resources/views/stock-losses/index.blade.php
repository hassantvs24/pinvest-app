@extends('layouts.app')

@section('title', __('messages.stock_losses'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Report form --}}
        <div class="card p-5 mb-4">
            <h1 class="text-xl font-bold mb-1 flex items-center gap-2">
                <x-icon name="arrow-trending-down" class="w-6 h-6 text-rose-500" /> {{ __('messages.add_stock_loss') }}
            </h1>
            <p class="text-sm text-gray-500 mb-4">{{ __('messages.stock_loss_hint') }}</p>

            <form method="POST" action="{{ route('stock-losses.store') }}" class="space-y-4 js-confirm-submit">
                @csrf

                <div>
                    <label class="label"><x-icon name="cube" class="w-4 h-4" /> {{ __('messages.item') }}</label>
                    <select name="item_id" required class="input">
                        <option value="">{{ __('messages.select_option') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">{{ __('messages.quantity') }}</label>
                        <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="0.001" step="any" required class="input">
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 flex items-center gap-1.5">
                            <x-icon name="calendar-days" class="w-4 h-4" /> {{ __('messages.auto_today_hint') }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="label">{{ __('messages.reason') }} ({{ __('messages.optional') }})</label>
                    <textarea name="note" rows="2" placeholder="{{ __('messages.stock_loss_reason_placeholder') }}" class="input">{{ old('note') }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-lg">
                    <x-icon name="check" class="w-5 h-5" /> {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>

        {{-- Own losses --}}
        <h2 class="section-title"><x-icon name="clipboard-document-list" class="w-5 h-5 text-emerald-600" /> {{ __('messages.my_entries') }}</h2>
        <div class="space-y-3">
            @forelse($losses as $loss)
                <div class="card">
                    <div class="font-bold flex items-center gap-1.5">
                        <x-icon name="arrow-trending-down" class="w-4 h-4 text-rose-500" /> {{ $loss->item->name ?? '—' }} × {{ \App\Support\ItemUnits::formatQuantity((float) $loss->quantity) }} {{ \App\Support\ItemUnits::label($loss->item->unit ?? null) }}
                    </div>
                    <div class="text-sm text-gray-500 flex items-center gap-1.5 flex-wrap">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($loss->entry_date) }}
                        @php $lst = $loss->status->value; @endphp
                        <span class="badge {{ $lst === 'pending' ? 'badge-pending' : ($lst === 'confirmed' ? 'badge-confirmed' : 'badge-rejected') }}">
                            <x-icon :name="$lst === 'pending' ? 'clock' : ($lst === 'confirmed' ? 'check' : 'x-mark')" class="w-3.5 h-3.5" /> {{ __('messages.'.$lst) }}
                        </span>
                    </div>
                    @if($loss->note)
                        <div class="text-sm text-gray-500 flex items-center gap-1.5">
                            <x-icon name="document-text" class="w-4 h-4" /> {{ $loss->note }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <x-icon name="clipboard-document-list" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $losses->links() }}</div>
    </div>

@endsection
