@extends('layouts.app')

@section('title', __('messages.'.$config['label']))

@section('content')
    <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
        <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.entries_status_legend') }}
    </p>

    {{-- Type switcher --}}
    <div class="grid grid-cols-3 gap-2 mb-3">
        @foreach(['expenses', 'purchases', 'sales'] as $t)
            @php $tIcon = $t === 'expenses' ? 'currency-dollar' : ($t === 'purchases' ? 'shopping-cart' : 'banknotes'); @endphp
            <a href="{{ route('entries.index', ['type' => $t]) }}"
               class="inline-flex items-center justify-center gap-1.5 font-bold rounded-xl py-2.5 text-sm transition active:scale-95 {{ $type === $t ? 'bg-brand-gradient text-white shadow-md shadow-emerald-600/25' : 'bg-white text-gray-600 ring-1 ring-gray-200' }}">
                <x-icon :name="$tIcon" class="w-4 h-4" /> {{ __('messages.'.$t) }}
            </a>
        @endforeach
    </div>

    {{-- Status filter tabs --}}
    <div class="flex gap-2 mb-3 overflow-x-auto">
        @foreach(['all', 'pending', 'confirmed', 'rejected'] as $s)
            <a href="{{ route('entries.index', ['type' => $type, 'status' => $s]) }}"
               class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition active:scale-95
                   {{ $status === $s
                       ? ($s === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/25' : ($s === 'confirmed' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/25' : ($s === 'rejected' ? 'bg-red-500 text-white shadow-md shadow-red-500/25' : 'bg-gray-700 text-white shadow-md shadow-gray-700/25')))
                       : 'bg-white text-gray-600 ring-1 ring-gray-200' }}">
                {{ __('messages.'.$s) }}
            </a>
        @endforeach
    </div>

    <a href="{{ route('entries.create', ['type' => $type]) }}"
       class="btn-primary w-full mb-3">
        <x-icon name="plus" class="w-5 h-5" /> {{ __('messages.add_'.rtrim($type, 's')) }}
    </a>

    {{-- Entry list --}}
    <div class="space-y-3">
        @forelse($entries as $entry)
            <div class="card">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">{{ $entry->{$config['item_relation']}->name ?? '—' }}</div>
                        @if($type !== 'expenses')
                            <div class="text-sm text-gray-500">
                                {{ \App\Support\ItemUnits::formatQuantity((float) $entry->quantity) }} {{ \App\Support\ItemUnits::label($entry->{$config['item_relation']}->unit ?? null) }} × {{ \App\Support\Money::format((float) $entry->unit_price) }}
                            </div>
                        @endif
                        @if($entry->note)
                            <div class="text-sm text-gray-500 flex items-center gap-1.5">
                                <x-icon name="document-text" class="w-4 h-4" /> {{ $entry->note }}
                            </div>
                        @endif
                        <div class="text-sm text-gray-500 flex items-center gap-1.5">
                            <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($entry->entry_date) }}
                            <x-icon name="clock" class="w-4 h-4" /> {{ $entry->created_at->format(\App\Support\DateFormats::TIME) }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold">
                            {{ \App\Support\Money::format((float) ($type === 'expenses' ? $entry->amount : $entry->total)) }}
                        </div>
                        @php $st = $entry->status->value; @endphp
                        <span class="badge mt-1 {{ $st === 'pending' ? 'badge-pending' : ($st === 'confirmed' ? 'badge-confirmed' : 'badge-rejected') }}">
                            <x-icon :name="$st === 'pending' ? 'clock' : ($st === 'confirmed' ? 'check' : 'x-mark')" class="w-3.5 h-3.5" /> {{ __('messages.'.$st) }}
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="clipboard-document-list" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
