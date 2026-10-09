@extends('layouts.app')

@section('title', __('messages.stock_losses'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Report form --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 mb-4">
            <h1 class="text-xl font-bold mb-1">📉 {{ __('messages.add_stock_loss') }}</h1>
            <p class="text-sm text-gray-500 mb-4">{{ __('messages.stock_loss_hint') }}</p>

            <form method="POST" action="{{ route('stock-losses.store') }}" class="space-y-4 js-confirm-submit">
                @csrf

                <div>
                    <label class="block text-sm font-medium mb-1">📦 {{ __('messages.item') }}</label>
                    <select name="item_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">{{ __('messages.select_option') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('messages.quantity') }}</label>
                        <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" step="1" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <input type="hidden" name="entry_date" value="{{ $today }}">
                    <p class="text-xs text-gray-500">📅 {{ __('messages.auto_today_hint') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.reason') }} ({{ __('messages.optional') }})</label>
                    <textarea name="note" rows="2" placeholder="{{ __('messages.stock_loss_reason_placeholder') }}"
                              class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('note') }}</textarea>
                </div>

                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-xl py-3">
                    📩 {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>

        {{-- Own losses --}}
        <h2 class="font-bold text-lg mb-2">📋 {{ __('messages.my_entries') }}</h2>
        <div class="space-y-3">
            @forelse($losses as $loss)
                <div class="bg-white rounded-xl shadow p-4">
                    <div class="font-bold">📉 {{ $loss->item->name ?? '—' }} × {{ $loss->quantity }} {{ \App\Support\ItemUnits::label($loss->item->unit ?? null) }}</div>
                    <div class="text-sm text-gray-500">
                        📅 {{ $loss->entry_date->format(\App\Support\DateFormats::DATE) }}
                        @php $lst = $loss->status->value; @endphp
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full
                            {{ $lst === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($lst === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                            {{ $lst === 'pending' ? '⏳' : ($lst === 'confirmed' ? '✅' : '❌') }} {{ __('messages.'.$lst) }}
                        </span>
                    </div>
                    @if($loss->note)
                        <div class="text-sm text-gray-500">📝 {{ $loss->note }}</div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                    📋 {{ __('messages.no_entries') }}
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $losses->links() }}</div>
    </div>

@endsection
