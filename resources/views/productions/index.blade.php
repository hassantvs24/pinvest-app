@extends('layouts.app')

@section('title', __('messages.productions'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Create form --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 mb-4">
            <h1 class="text-xl font-bold mb-1">🏭 {{ __('messages.add_production') }}</h1>
            <p class="text-sm text-gray-500 mb-2">{{ __('messages.production_hint') }}</p>
            <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mb-3">⏳ {{ __('messages.production_pending_hint') }}</p>

            <form method="POST" action="{{ route('productions.store') }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Finished good output rows (dynamic add/remove) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">👕 {{ __('messages.output_items') }}</label>
                    <div id="output_rows" class="space-y-2">
                        <div class="output-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="outputs[0][item_id]" required class="border border-gray-300 rounded-lg px-3 py-3 bg-white">
                                <option value="">{{ __('messages.output_item') }}</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                                @endforeach
                            </select>
                            <input type="number" name="outputs[0][quantity]" min="1" step="1" required placeholder="{{ __('messages.quantity') }}"
                                   class="border border-gray-300 rounded-lg px-3 py-3">
                            <button type="button" class="js-remove-output bg-red-100 text-red-700 rounded-lg font-bold" title="{{ __('messages.remove') }}">✕</button>
                        </div>
                    </div>
                    <button type="button" id="js-add-output" class="mt-2 w-full border-2 border-dashed border-emerald-300 text-emerald-700 font-bold rounded-lg py-2">
                        ➕ {{ __('messages.add_output') }}
                    </button>
                    <p class="text-xs text-gray-500 mt-1">{{ __('messages.output_split_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1">⚖️ {{ __('messages.production_unit_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1">💰 {{ __('messages.production_value_hint') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">🧾 {{ __('messages.extra_cost') }} ({{ __('messages.optional') }})</label>
                        <input type="number" name="extra_cost" value="{{ old('extra_cost') }}" step="0.01" min="0"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-xs bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg px-3 py-2 mt-1">⚠️ {{ __('messages.labour_double_count_hint') }}</p>
                    </div>
                    <input type="hidden" name="entry_date" value="{{ $today }}">
                    <p class="text-xs text-gray-500">📅 {{ __('messages.auto_today_hint') }}</p>
                </div>

                {{-- Raw material rows (dynamic add/remove) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">🧵 {{ __('messages.input_items') }} ({{ __('messages.optional') }})</label>
                    <div id="component_rows" class="space-y-2">
                        <div class="component-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="components[0][item_id]" required class="js-component-select border border-gray-300 rounded-lg px-3 py-3 bg-white">
                                <option value="">{{ __('messages.item') }}</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-available="{{ $available[$item->id] ?? 0 }}" data-unit="{{ \App\Support\ItemUnits::label($item->unit) }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
                                @endforeach
                            </select>
                            <input type="number" name="components[0][quantity]" min="1" step="1" required placeholder="{{ __('messages.quantity') }}"
                                   class="border border-gray-300 rounded-lg px-3 py-3">
                            <button type="button" class="js-remove-row bg-red-100 text-red-700 rounded-lg font-bold" title="{{ __('messages.remove') }}">✕</button>
                        </div>
                    </div>
                    <button type="button" id="js-add-row" class="mt-2 w-full border-2 border-dashed border-emerald-300 text-emerald-700 font-bold rounded-lg py-2">
                        ➕ {{ __('messages.add_material') }}
                    </button>
                    <p class="text-xs text-gray-500 mt-1">⚖️ {{ __('messages.production_unit_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-1">📦 {{ __('messages.production_stock_hint') }}</p>
                    <p id="component_stock_hint" class="text-xs text-gray-600"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('note') }}</textarea>
                </div>

                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-lg rounded-xl py-3">
                    📩 {{ __('messages.submit_entry') }}
                </button>
            </form>
        </div>

        {{-- Own productions list --}}
        <h2 class="font-bold text-lg mb-2">📋 {{ __('messages.my_entries') }}</h2>
        <div class="space-y-3">
            @forelse($productions as $production)
                <div class="bg-white rounded-xl shadow p-4">
                    <div class="font-bold">👕 {{ $production->outputs->map(fn ($o) => $o->item->name.' × '.$o->quantity)->implode(', ') }}</div>
                    <div class="text-sm text-gray-500">
                        📅 {{ $production->entry_date->format(\App\Support\DateFormats::DATE) }}
                        @php $pst = $production->status->value; @endphp
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full
                            {{ $pst === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($pst === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                            {{ $pst === 'pending' ? '⏳' : ($pst === 'confirmed' ? '✅' : '❌') }} {{ __('messages.'.$pst) }}
                        </span>
                    </div>
                    @foreach($production->components as $component)
                        <div class="text-sm text-gray-600">
                            🧵 {{ $component->item->name ?? '—' }}
                            — {{ $component->quantity }} {{ \App\Support\ItemUnits::label($component->item->unit ?? null) }}
                        </div>
                    @endforeach
                    @if((float) $production->extra_cost > 0)
                        <div class="text-sm text-violet-700">🧾 {{ __('messages.extra_cost') }}: ৳{{ number_format((float) $production->extra_cost, 2) }}</div>
                    @endif
                    @if($production->note)
                        <div class="text-sm text-gray-500">📝 {{ $production->note }}</div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                    📋 {{ __('messages.no_entries') }}
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

            // Remove a raw-material row (keep at least one).
            $(document).on('click', '.js-remove-row', function () {
                if ($('.component-row').length > 1) {
                    $(this).closest('.component-row').remove();
                }
            });

            // Live hint: how much of the selected material is available.
            function updateComponentHint() {
                var selected = $('.js-component-select').first().find('option:selected');
                var available = parseFloat(selected.data('available'));
                var hint = $('#component_stock_hint');
                if (isNaN(available) || available === undefined) {
                    hint.text('');
                    return;
                }
                var unit = $('.js-component-select').first().find('option:selected').data('unit') || '';
                var text = @json(__('messages.available_stock_hint')).replace(':available', available).replace(':unit', unit);
                hint.text('📦 ' + text);
                hint.toggleClass('text-red-600 font-bold', available <= 0);
            }
            $(document).on('change', '.js-component-select', updateComponentHint);
            updateComponentHint();
        });
    </script>
@endsection
