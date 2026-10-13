@extends('layouts.app')

@section('title', __('messages.partner_cash'))

@section('content')
    <div class="card mb-4 border-l-4 border-l-emerald-600">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="building-library" class="w-4 h-4" /> {{ __('messages.cash_in_hand') }}
        </div>
        <div class="text-2xl font-bold text-emerald-700">{{ \App\Support\Money::format($cashInHand) }}</div>
        <p class="text-xs text-gray-500 mt-1">{{ __('messages.cash_pool_hint') }}</p>
    </div>

    <div class="card mb-4 border-l-4 border-l-cyan-600">
        <div class="text-sm text-gray-500 flex items-center gap-1.5">
            <x-icon name="receipt-percent" class="w-4 h-4" /> {{ __('messages.cash_with_partners') }}
        </div>
        <div class="text-2xl font-bold text-cyan-700">{{ \App\Support\Money::format($cashWithPartners) }}</div>
        <p class="text-xs text-gray-500 mt-1">{{ __('messages.partner_cash_hint') }}</p>
        <p class="text-xs text-gray-500 mt-1 flex items-start gap-1.5">
            <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.partner_cash_ledger_hint') }}
        </p>
    </div>

    {{-- Cash held per partner --}}
    @php
        $partnersWithCash = $partners->filter(fn ($p) => (float) ($outstanding[$p->id] ?? 0) != 0);
    @endphp
    @if($partnersWithCash->isNotEmpty())
        <div class="card mb-4">
            <div class="font-bold mb-2 flex items-center gap-2">
                <x-icon name="user-group" class="w-5 h-5 text-emerald-600" /> {{ __('messages.outstanding_by_partner') }}
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($partnersWithCash as $partner)
                    @php $held = (float) ($outstanding[$partner->id] ?? 0); @endphp
                    <li class="py-2 flex items-center justify-between gap-2">
                        <span>{{ $partner->name }}</span>
                        <span class="font-bold {{ $held < 0 ? 'text-rose-600' : 'text-cyan-700' }}">{{ \App\Support\Money::format($held) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-gray-500 mt-2 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.negative_outstanding_hint') }}
            </p>
        </div>
    @endif

    {{-- Record movements (tabbed) --}}
    @php
        $tabCount = $pendingSettlements->isNotEmpty() ? 4 : 3;
        $gridClass = $tabCount === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3';
        $tabs = [
            ['id' => 'hold', 'icon' => 'plus', 'label' => __('messages.partner_cash_tab_hold')],
            ['id' => 'return', 'icon' => 'arrow-trending-down', 'label' => __('messages.partner_cash_tab_return')],
            ['id' => 'spent', 'icon' => 'document-text', 'label' => __('messages.partner_cash_tab_spent')],
        ];
        if ($pendingSettlements->isNotEmpty()) {
            $tabs[] = ['id' => 'adjust', 'icon' => 'receipt-percent', 'label' => __('messages.partner_cash_tab_adjust')];
        }
        // After a failed submit, reopen the tab the owner was on: the
        // commission form is the only one with its own error field; the
        // cash-adjustment form is identified by its direction select.
        $activeTab = $errors->has('commission_settlement_id') ? 'adjust'
            : (old('direction') !== null ? 'spent' : 'hold');
        $tabBtnActive = 'bg-cyan-600 text-white';
        $tabBtnInactive = 'bg-gray-100 text-gray-600 hover:bg-gray-200';
    @endphp

    <div class="mb-4" id="partner-cash-tabs">
        <div class="grid {{ $gridClass }} gap-1 mb-3">
            @foreach($tabs as $tab)
                <button type="button" data-tab="{{ $tab['id'] }}"
                        class="tab-btn rounded-lg py-2.5 text-xs font-bold text-center inline-flex items-center justify-center gap-1.5 {{ $tab['id'] === $activeTab ? $tabBtnActive : $tabBtnInactive }}">
                    <x-icon :name="$tab['icon']" class="w-4 h-4" /> {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Give / keep cash with partner --}}
        <div class="tab-panel card {{ $activeTab === 'hold' ? '' : 'hidden' }}" data-panel="hold">
            <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.partner_cash_hold_help') }}
            </p>
            <form method="POST" action="{{ route('owner.partner-cash.hold') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label">{{ __('messages.partner') }}</label>
                    <select name="user_id" required class="input">
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('user_id') == $partner->id)>{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">{{ __('messages.amount') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.date') }}</label>
                    <input type="date" name="occurred_at" value="{{ old('occurred_at', $today) }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <input type="text" name="note" value="{{ old('note') }}" placeholder="{{ __('messages.partner_cash_hold') }}" class="input">
                </div>
                <button type="submit" class="btn-primary w-full">
                    <x-icon name="plus" class="w-5 h-5" /> {{ __('messages.partner_cash_hold') }}
                </button>
            </form>
        </div>

        {{-- Partner returned cash --}}
        <div class="tab-panel card {{ $activeTab === 'return' ? '' : 'hidden' }}" data-panel="return">
            <form method="POST" action="{{ route('owner.partner-cash.return') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label">{{ __('messages.partner') }}</label>
                    <select name="user_id" required class="input">
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('user_id') == $partner->id)>{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">{{ __('messages.amount') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.date') }}</label>
                    <input type="date" name="occurred_at" value="{{ old('occurred_at', $today) }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn-sale w-full">
                    <x-icon name="arrow-trending-down" class="w-5 h-5" /> {{ __('messages.partner_cash_return') }}
                </button>
            </form>
        </div>

        {{-- Cash adjustment (increase or decrease the held cash) --}}
        <div class="tab-panel card {{ $activeTab === 'spent' ? '' : 'hidden' }}" data-panel="spent">
            <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.partner_cash_spent_help') }}
            </p>
            <form method="POST" action="{{ route('owner.partner-cash.cash-adjust') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label">{{ __('messages.partner') }}</label>
                    <select name="user_id" required class="input">
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('user_id') == $partner->id)>{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">{{ __('messages.cash_adjustment_direction') }}</label>
                    <select name="direction" required class="input">
                        <option value="subtract" @selected(old('direction', 'subtract') === 'subtract')>{{ __('messages.cash_adjustment_subtract') }}</option>
                        <option value="add" @selected(old('direction') === 'add')>{{ __('messages.cash_adjustment_add') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">{{ __('messages.amount') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.date') }}</label>
                    <input type="date" name="occurred_at" value="{{ old('occurred_at', $today) }}" required class="input">
                </div>
                <div>
                    <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn-purchase w-full">
                    <x-icon name="document-text" class="w-5 h-5" /> {{ __('messages.partner_cash_spent') }}
                </button>
            </form>
        </div>

        {{-- Settle commission from held cash --}}
        @if($pendingSettlements->isNotEmpty())
            <div class="tab-panel card {{ $activeTab === 'adjust' ? '' : 'hidden' }}" data-panel="adjust">
                <p class="text-xs text-gray-500 mb-3 flex items-start gap-1.5">
                    <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5" /> {{ __('messages.partner_cash_adjust_help') }}
                </p>
                <form method="POST" action="{{ route('owner.partner-cash.adjust') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="label">{{ __('messages.select_settlement') }}</label>
                        <select name="commission_settlement_id" required class="input">
                            @foreach($pendingSettlements as $settlement)
                                <option value="{{ $settlement->id }}" @selected(old('commission_settlement_id') == $settlement->id)>
                                    {{ $settlement->user->name }} — {{ $settlement->period?->label ?? $settlement->period_start->format('M Y') }}
                                    ({{ \App\Support\Money::format((float) $settlement->amount) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ __('messages.date') }}</label>
                        <input type="date" name="occurred_at" value="{{ old('occurred_at', $today) }}" required class="input">
                    </div>
                    <div>
                        <label class="label">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                        <textarea name="note" rows="2" class="input">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn-payout w-full">
                        <x-icon name="receipt-percent" class="w-5 h-5" /> {{ __('messages.partner_cash_adjust') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

    <script>
        $(function () {
            $(document).on('click', '#partner-cash-tabs .tab-btn', function () {
                var tab = $(this).data('tab');
                $('#partner-cash-tabs .tab-btn').removeClass('bg-cyan-600 text-white').addClass('bg-gray-100 text-gray-600 hover:bg-gray-200');
                $(this).removeClass('bg-gray-100 text-gray-600 hover:bg-gray-200').addClass('bg-cyan-600 text-white');
                $('#partner-cash-tabs .tab-panel').addClass('hidden');
                $('#partner-cash-tabs .tab-panel[data-panel="' + tab + '"]').removeClass('hidden');
            });
        });
    </script>

    {{-- Movement ledger --}}
    <div class="space-y-3">
        @forelse($movements as $movement)
            @php
                $isHold = $movement->type === \App\Enums\CashMovementType::Hold;
                $labels = [
                    \App\Enums\CashMovementType::Hold->value => __('messages.partner_cash_hold'),
                    \App\Enums\CashMovementType::Return->value => __('messages.partner_cash_return'),
                    \App\Enums\CashMovementType::Spent->value => __('messages.partner_cash_badge_spent'),
                    \App\Enums\CashMovementType::CommissionAdjustment->value => __('messages.partner_cash_adjust'),
                ];
                $colors = [
                    \App\Enums\CashMovementType::Hold->value => 'bg-emerald-100 text-emerald-800',
                    \App\Enums\CashMovementType::Return->value => 'bg-blue-100 text-blue-800',
                    \App\Enums\CashMovementType::Spent->value => 'bg-orange-100 text-orange-800',
                    \App\Enums\CashMovementType::CommissionAdjustment->value => 'bg-violet-100 text-violet-800',
                ];
            @endphp
            <div class="card flex items-start justify-between gap-2">
                <div>
                    <div class="font-medium">{{ $movement->user->name }}</div>
                    @if($movement->note)
                        <div class="text-sm text-gray-600 flex items-center gap-1.5">
                            <x-icon name="document-text" class="w-4 h-4" /> {{ $movement->note }}
                        </div>
                    @endif
                    <div class="text-sm text-gray-500 flex items-center gap-1.5">
                        <x-icon name="calendar-days" class="w-4 h-4" /> {{ $movement->occurred_at->format('d M Y, H:i') }}
                    </div>
                    <span class="badge mt-1 {{ $colors[$movement->type->value] }}">
                        {{ $labels[$movement->type->value] }}
                    </span>
                </div>
                <div class="text-lg font-bold shrink-0 {{ $isHold ? 'text-emerald-700' : 'text-rose-600' }}">
                    {{ $isHold ? '+' : '−' }}{{ \App\Support\Money::format((float) $movement->amount) }}
                </div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="receipt-percent" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_entries') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
@endsection
