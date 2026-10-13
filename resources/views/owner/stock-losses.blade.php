@extends('layouts.app')

@section('title', __('messages.stock_losses'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Report form --}}
        <div class="card p-5 mb-4">
            <h1 class="text-xl font-bold mb-1 flex items-center gap-2">
                <x-icon name="arrow-trending-down" class="w-6 h-6 text-rose-500" /> {{ __('messages.add_stock_loss') }}
            </h1>
            <p class="text-sm text-gray-500 mb-2">{{ __('messages.stock_loss_hint') }}</p>
            <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mb-3 flex items-start gap-1.5">
                <x-icon name="clock" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.stock_loss_pending_hint_owner') }}
            </p>

            <form method="POST" action="{{ route('owner.stock-losses.store') }}" class="space-y-4 js-confirm-submit">
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
                        <label class="label">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required class="input">
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

        {{-- All losses --}}
        <h2 class="section-title"><x-icon name="clipboard-document-list" class="w-5 h-5 text-emerald-600" /> {{ __('messages.stock_losses') }}</h2>
        <div class="space-y-3">
            @forelse($losses as $loss)
                <div class="card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-bold flex items-center gap-1.5">
                                <x-icon name="arrow-trending-down" class="w-4 h-4 text-rose-500" /> {{ $loss->item->name ?? '—' }} × {{ \App\Support\ItemUnits::formatQuantity((float) $loss->quantity) }} {{ \App\Support\ItemUnits::label($loss->item->unit ?? null) }}
                            </div>
                            <div class="text-sm text-gray-500 flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1.5"><x-avatar :user="$loss->user" size="20" /> {{ $loss->user->name }}</span>
                                · <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($loss->entry_date) }}
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
                        <div class="flex items-center gap-1 shrink-0">
                            @if($lst === 'pending')
                                <form method="POST" action="{{ route('owner.stock-losses.confirm', ['stock_loss' => $loss->id]) }}" class="js-confirm-approve">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon bg-green-600 text-white shadow-md shadow-green-600/25 hover:bg-green-700" title="{{ __('messages.confirm') }}">
                                        <x-icon name="check" class="w-5 h-5" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('owner.stock-losses.reject', ['stock_loss' => $loss->id]) }}" class="js-confirm-reject">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon bg-red-500 text-white shadow-md shadow-red-500/25 hover:bg-red-600" title="{{ __('messages.reject') }}">
                                        <x-icon name="x-mark" class="w-5 h-5" />
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('owner.stock-losses.destroy', ['stock_loss' => $loss->id]) }}" class="js-confirm-delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon bg-red-100 text-red-700 ring-1 ring-red-200 hover:bg-red-200" title="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-5 h-5" />
                                </button>
                            </form>
                        </div>
                    </div>
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
