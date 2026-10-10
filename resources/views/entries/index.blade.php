@extends('layouts.app')

@section('title', __('messages.'.$config['label']))

@section('content')
    <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.entries_status_legend') }}</p>

    {{-- Type switcher --}}
    <div class="grid grid-cols-3 gap-2 mb-3">
        @foreach(['expenses', 'purchases', 'sales'] as $t)
            <a href="{{ route('entries.index', ['type' => $t]) }}"
               class="text-center font-bold rounded-xl py-2 text-sm {{ $type === $t ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600' }}">
                {{ $t === 'expenses' ? '💸' : ($t === 'purchases' ? '🛒' : '💵') }} {{ __('messages.'.$t) }}
            </a>
        @endforeach
    </div>

    {{-- Status filter tabs --}}
    <div class="flex gap-2 mb-3 overflow-x-auto">
        @foreach(['all', 'pending', 'confirmed', 'rejected'] as $s)
            <a href="{{ route('entries.index', ['type' => $type, 'status' => $s]) }}"
               class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap
                   {{ $status === $s
                       ? ($s === 'pending' ? 'bg-yellow-500 text-white' : ($s === 'confirmed' ? 'bg-green-600 text-white' : ($s === 'rejected' ? 'bg-red-500 text-white' : 'bg-gray-700 text-white')))
                       : 'bg-white text-gray-600' }}">
                {{ __('messages.'.$s) }}
            </a>
        @endforeach
    </div>

    <a href="{{ route('entries.create', ['type' => $type]) }}"
       class="block w-full bg-emerald-700 hover:bg-emerald-800 text-white text-center font-bold rounded-xl py-3 mb-3">
        ➕ {{ __('messages.add_'.rtrim($type, 's')) }}
    </a>

    {{-- Entry list --}}
    <div class="space-y-3">
        @forelse($entries as $entry)
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">{{ $entry->{$config['item_relation']}->name ?? '—' }}</div>
                        @if($type !== 'expenses')
                            <div class="text-sm text-gray-500">
                                {{ \App\Support\ItemUnits::formatQuantity((float) $entry->quantity) }} {{ \App\Support\ItemUnits::label($entry->{$config['item_relation']}->unit ?? null) }} × {{ \App\Support\Money::format((float) $entry->unit_price) }}
                            </div>
                        @endif
                        @if($entry->note)
                            <div class="text-sm text-gray-500">📝 {{ $entry->note }}</div>
                        @endif
                        <div class="text-sm text-gray-500">
                            📅 {{ \App\Support\DateFormats::date($entry->entry_date) }}
                            🕐 {{ $entry->created_at->format(\App\Support\DateFormats::TIME) }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold">
                            {{ \App\Support\Money::format((float) ($type === 'expenses' ? $entry->amount : $entry->total)) }}
                        </div>
                        @php $st = $entry->status->value; @endphp
                        <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full
                            {{ $st === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($st === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                            {{ $st === 'pending' ? '⏳' : ($st === 'confirmed' ? '✅' : '❌') }} {{ __('messages.'.$st) }}
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                📋 {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
