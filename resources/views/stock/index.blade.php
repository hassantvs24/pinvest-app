@extends('layouts.app')

@section('title', __('messages.stock_page'))

@section('content')
    <h1 class="page-title flex items-center gap-2">
        <x-icon name="building-storefront" class="w-6 h-6 text-emerald-600" /> {{ __('messages.stock_page') }}
    </h1>
    <p class="text-xs text-gray-500 mb-4">{{ __('messages.partner_stock_hint') }}</p>

    <div class="card mb-6 overflow-hidden p-0">
        @if($rows === [])
            <p class="p-4 text-gray-500 text-sm">{{ __('messages.no_stock') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <span class="font-medium">{{ $row['item']->name }}</span>
                            <span class="text-xs text-gray-500">({{ \App\Support\ItemUnits::label($row['item']->unit) }})</span>
                            @if($row['available'] <= 0.00001)
                                <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-bold">{{ __('messages.stock_out') }}</span>
                            @endif
                            <div class="text-xs text-gray-500">
                                {{ __('messages.stock_reserved') }}: {{ rtrim(rtrim(number_format($row['reserved'], 2), '0'), '.') }}
                                ・{{ __('messages.stock_available_now') }}:
                                <span class="font-bold {{ $row['available'] <= 0.00001 ? 'text-red-600' : 'text-emerald-700' }}">
                                    {{ rtrim(rtrim(number_format($row['available'], 2), '0'), '.') }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right shrink-0 font-bold">
                            {{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }} {{ \App\Support\ItemUnits::label($row['item']->unit) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
