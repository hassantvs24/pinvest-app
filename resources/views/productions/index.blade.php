@extends('layouts.app')

@section('title', __('messages.productions'))

@section('content')
    <div class="max-w-xl mx-auto">
        {{-- Create form --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 mb-4">
            <h1 class="text-xl font-bold mb-1">🏭 {{ __('messages.add_production') }}</h1>
            <p class="text-sm text-gray-500 mb-4">{{ __('messages.production_hint') }}</p>

            <form method="POST" action="{{ route('productions.store') }}" class="space-y-4 js-confirm-submit">
                @csrf

                {{-- Finished good output rows (dynamic add/remove) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">👕 {{ __('messages.finished_goods') }}</label>
                    <div id="output_rows" class="space-y-2">
                        <div class="output-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="outputs[0][sale_item_id]" required class="border border-gray-300 rounded-lg px-3 py-3 bg-white">
                                <option value="">{{ __('messages.finished_good') }}</option>
                                @foreach($saleItems as $item)
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
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">🧾 {{ __('messages.extra_cost') }} ({{ __('messages.optional') }})</label>
                        <input type="number" name="extra_cost" value="{{ old('extra_cost') }}" step="0.01" min="0"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.labour_double_count_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('messages.date') }}</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $today) }}" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                {{-- Raw material rows (dynamic add/remove) --}}
                <div>
                    <label class="block text-sm font-medium mb-1">🧵 {{ __('messages.raw_materials') }} ({{ __('messages.optional') }})</label>
                    <div id="component_rows" class="space-y-2">
                        <div class="component-row grid grid-cols-[1fr_110px_44px] gap-2">
                            <select name="components[0][purchase_item_id]" required class="border border-gray-300 rounded-lg px-3 py-3 bg-white">
                                <option value="">{{ __('messages.purchase_item') }}</option>
                                @foreach($purchaseItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ \App\Support\ItemUnits::label($item->unit) }})</option>
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
                    <div class="font-bold">👕 {{ $production->outputs->map(fn ($o) => $o->saleItem->name.' × '.$o->quantity)->implode(', ') }}</div>
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
                            🧵 {{ $component->purchaseItem->name ?? '—' }}
                            — {{ $component->quantity }} {{ \App\Support\ItemUnits::label($component->purchaseItem->unit ?? null) }}
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
                $row.find('select').attr('name', 'outputs[' + rowIndex + '][sale_item_id]').val('');
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
                $row.find('select').attr('name', 'components[' + rowIndex + '][purchase_item_id]').val('');
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
        });
    </script>
@endsection
