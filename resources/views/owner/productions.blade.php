@extends('layouts.app')

@section('title', __('messages.productions'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Create form --}}
        <div class="card p-5 mb-4">
            <h1 class="text-xl font-bold mb-1 flex items-center gap-2">
                <x-icon name="building-office-2" class="w-6 h-6 text-emerald-600" /> {{ __('messages.add_production') }}
            </h1>
            <p class="text-sm text-gray-500 mb-2">{{ __('messages.production_hint') }}</p>
            <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mb-3 flex items-start gap-1.5">
                <x-icon name="clock" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.production_pending_hint_owner') }}
            </p>

            <form method="POST" action="{{ route('owner.productions.store') }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Finished good output rows (dynamic add/remove) --}}
                <div>
                    <label class="label"><x-icon name="tag" class="w-4 h-4" /> {{ __('messages.output_items') }}</label>
                    <div id="output_rows" class="space-y-2">
                        <div class="output-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="outputs[0][item_id]" required class="input-sm">
                                <option value="">{{ __('messages.output_item') }}</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                                @endforeach
                            </select>
                            <input type="number" name="outputs[0][quantity]" min="0.001" step="any" required placeholder="{{ __('messages.quantity') }}"
                                   class="input-sm">
                            <button type="button" class="js-remove-output bg-red-100 text-red-700 rounded-lg font-bold" title="{{ __('messages.remove') }}"><x-icon name="x-mark" class="w-4 h-4 mx-auto" /></button>
                        </div>
                    </div>
                    <button type="button" id="js-add-output" class="mt-2 w-full border-2 border-dashed border-emerald-300 text-emerald-700 font-bold rounded-xl py-2.5 flex items-center justify-center gap-1.5 active:scale-[0.98] transition">
                        <x-icon name="plus" class="w-4 h-4" /> {{ __('messages.add_output') }}
                    </button>
                    <p class="text-xs text-gray-500 mt-1">{{ __('messages.output_split_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1"><x-icon name="calculator" class="w-3.5 h-3.5" /> {{ __('messages.production_unit_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1"><x-icon name="banknotes" class="w-3.5 h-3.5" /> {{ __('messages.production_value_hint') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label"><x-icon name="document-text" class="w-4 h-4" /> {{ __('messages.extra_cost') }} ({{ __('messages.optional') }})</label>
                        <input type="number" name="extra_cost" value="{{ old('extra_cost') }}" step="0.01" min="0" class="input">
                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.labour_double_count_hint') }}</p>
                    </div>
                    <div>
                        <label class="label">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required class="input">
                    </div>
                </div>

                {{-- Raw material rows (dynamic add/remove) --}}
                <div>
                    <label class="label"><x-icon name="queue-list" class="w-4 h-4" /> {{ __('messages.input_items') }} ({{ __('messages.optional') }})</label>
                    <div id="component_rows" class="space-y-2">
                        <div class="component-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="components[0][item_id]" class="js-component-select input-sm">
                                <option value="">{{ __('messages.item') }}</option>
                                @foreach($componentItems as $item)
                                    <option value="{{ $item->id }}" data-available="{{ $available[$item->id] ?? 0 }}" data-unit="{{ \App\Support\ItemUnits::label($item->unit) }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                                @endforeach
                            </select>
                            <input type="number" name="components[0][quantity]" min="0.001" step="any" placeholder="{{ __('messages.quantity') }}"
                                   class="input-sm">
                            <button type="button" class="js-remove-row bg-red-100 text-red-700 rounded-lg font-bold" title="{{ __('messages.remove') }}"><x-icon name="x-mark" class="w-4 h-4 mx-auto" /></button>
                        </div>
                    </div>
                    @if($componentItems->isEmpty())
                        <p class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 mt-2 flex items-start gap-1.5">
                            <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.no_component_stock') }}
                        </p>
                    @endif
                    <button type="button" id="js-add-row" class="mt-2 w-full border-2 border-dashed border-emerald-300 text-emerald-700 font-bold rounded-xl py-2.5 flex items-center justify-center gap-1.5 active:scale-[0.98] transition">
                        <x-icon name="plus" class="w-4 h-4" /> {{ __('messages.add_material') }}
                    </button>
                    <p class="text-xs text-gray-500 mt-1"><x-icon name="calculator" class="w-3.5 h-3.5" /> {{ __('messages.production_unit_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1"><x-icon name="cube" class="w-3.5 h-3.5" /> {{ __('messages.production_stock_hint') }}</p>
                    <p id="component_stock_hint" class="text-xs"></p>
                </div>

                <div>
                    <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-lg">
                    <x-icon name="check" class="w-5 h-5" /> {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>

        {{-- Productions list --}}
        <h2 class="section-title"><x-icon name="clipboard-document-list" class="w-5 h-5 text-emerald-600" /> {{ __('messages.productions') }}</h2>
        <div class="space-y-3">
            @forelse($productions as $production)
                <div class="card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-bold flex items-center gap-1.5">
                                <x-icon name="tag" class="w-4 h-4 text-emerald-600" /> {{ $production->outputs->map(fn ($o) => $o->item->name.' × '.\App\Support\ItemUnits::formatQuantity((float) $o->quantity))->implode(', ') }}
                            </div>
                            <div class="text-sm text-gray-500 flex items-center gap-1.5 flex-wrap">
                                <x-icon name="calendar-days" class="w-4 h-4" /> {{ \App\Support\DateFormats::dateTime($production->entry_date) }}
                                · <x-icon name="user-circle" class="w-4 h-4" /> {{ $production->user->name }}
                                @php $pst = $production->status->value; @endphp
                                <span class="badge {{ $pst === 'pending' ? 'badge-pending' : ($pst === 'confirmed' ? 'badge-confirmed' : 'badge-rejected') }}">
                                    <x-icon :name="$pst === 'pending' ? 'clock' : ($pst === 'confirmed' ? 'check' : 'x-mark')" class="w-3.5 h-3.5" /> {{ __('messages.'.$pst) }}
                                </span>
                            </div>
                            @foreach($production->components as $component)
                                <div class="text-sm text-gray-600 flex items-center gap-1.5">
                                    <x-icon name="queue-list" class="w-4 h-4" /> {{ $component->item->name ?? '—' }}
                                    — {{ \App\Support\ItemUnits::formatQuantity((float) $component->quantity) }} {{ \App\Support\ItemUnits::label($component->item->unit ?? null) }}
                                </div>
                            @endforeach
                            @if((float) $production->extra_cost > 0)
                                <div class="text-sm text-violet-700 flex items-center gap-1.5">
                                    <x-icon name="document-text" class="w-4 h-4" /> {{ __('messages.extra_cost') }}: {{ \App\Support\Money::format((float) $production->extra_cost) }}
                                </div>
                            @endif
                            @if($production->note)
                                <div class="text-sm text-gray-500 flex items-center gap-1.5">
                                    <x-icon name="document-text" class="w-4 h-4" /> {{ $production->note }}
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($pst === 'pending')
                                <form method="POST" action="{{ route('owner.productions.confirm', ['production' => $production->id]) }}" class="js-confirm-approve">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon bg-green-600 text-white shadow-md shadow-green-600/25 hover:bg-green-700" title="{{ __('messages.confirm') }}">
                                        <x-icon name="check" class="w-5 h-5" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('owner.productions.reject', ['production' => $production->id]) }}" class="js-confirm-reject">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon bg-red-500 text-white shadow-md shadow-red-500/25 hover:bg-red-600" title="{{ __('messages.reject') }}">
                                        <x-icon name="x-mark" class="w-5 h-5" />
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('owner.productions.destroy', ['production' => $production->id]) }}" class="js-confirm-delete">
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

        <div class="mt-4">{{ $productions->links() }}</div>
    </div>

    <script>
        $(function () {
            var rowIndex = 1;

            // Add a finished-good output row (clone of the first, re-indexed).
            $(document).on('click', '#js-add-output', function () {
                var $row = $('.output-row').first().clone();
                $row.find('select').attr('name', 'outputs[' + rowIndex + '][item_id]').val('');
                $row.find('input').attr('name', 'outputs[' + rowIndex + '][quantity]').val('');
                rowIndex++;
                $('#output_rows').append($row);
            });

            // Remove an output row (keep at least one).
            $(document).on('click', '.js-remove-output', function () {
                if ($('.output-row').length > 1) {
                    $(this).closest('.output-row').remove();
                }
            });

            // Add a raw-material row (clone of the first row, re-indexed).
            $(document).on('click', '#js-add-row', function () {
                var $row = $('.component-row').first().clone();
                $row.find('select').attr('name', 'components[' + rowIndex + '][item_id]').val('');
                $row.find('input').attr('name', 'components[' + rowIndex + '][quantity]').val('');
                rowIndex++;
                $('#component_rows').append($row);
            });

            // Remove a raw-material row (rows are optional — zero is fine).
            $(document).on('click', '.js-remove-row', function () {
                $(this).closest('.component-row').remove();
                updateComponentHint();
            });

            // Live hint: how much of the selected material is available.
            function updateComponentHint() {
                var selected = $('.js-component-select').first().find('option:selected');
                var available = parseFloat(selected.data('available'));
                var hint = $('#component_stock_hint');
                if (isNaN(available) || available === undefined) {
                    hint.text('').removeClass('text-red-600 text-emerald-700 font-bold');
                    return;
                }
                var unit = $('.js-component-select').first().find('option:selected').data('unit') || '';
                var text = @json(__('messages.available_stock_hint')).replace(':available', available).replace(':unit', unit);
                hint.text(text);
                hint.toggleClass('text-emerald-700 font-bold', available > 0);
                hint.toggleClass('text-red-600 font-bold', available <= 0);
            }
            $(document).on('change', '.js-component-select', updateComponentHint);
            updateComponentHint();
        });
    </script>
@endsection
