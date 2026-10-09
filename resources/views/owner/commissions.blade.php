@extends('layouts.app')

@section('title', __('messages.commissions'))

@section('content')
    {{-- Rate total hint --}}
    @if($totalRate > 100)
        <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">
            ⚠️ {{ __('messages.rate_over_100', ['total' => number_format($totalRate, 2)]) }}
        </div>
    @endif

    {{-- 1. Open period / close period --}}
    @if($openPeriod)
        <div class="bg-emerald-50 border border-emerald-300 rounded-xl shadow p-4 mb-6">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-bold text-emerald-800">🟢
                    {{ __('messages.current_period') }}{{ $openPeriod->label ? ' — '.$openPeriod->label : '' }}
                </h2>
                <span class="text-xs px-2 py-1 rounded-full bg-emerald-200 text-emerald-800">
                    {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                </span>
            </div>
            <ul class="text-sm text-gray-600 space-y-1 mb-3">
                <li>📅 {{ __('messages.opened_on') }}: {{ $openPeriod->opened_at->format('d M Y') }}</li>
                @if($openPeriod->opening_cash !== null)
                    <li>🏦 {{ __('messages.opening_cash') }}: ৳{{ number_format((float) $openPeriod->opening_cash, 2) }}</li>
                @endif
                <li>🏦 {{ __('messages.cash_in_hand') }}: ৳{{ number_format($cashInHand, 2) }}</li>
                @if($openPeriod->note)
                    <li>📝 {{ $openPeriod->note }}</li>
                @endif
            </ul>

            <a href="{{ route('owner.commissions.close_preview') }}"
               class="block w-full bg-emerald-700 hover:bg-emerald-800 text-white text-center font-bold rounded-xl py-3">
                🔒 {{ __('messages.close_period') }}
            </a>
            <p class="text-xs text-gray-500 mt-2">💡 {{ __('messages.close_period_hint') }}</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow p-4 mb-6">
            <h2 class="font-bold mb-1">🟢 {{ __('messages.open_period') }}</h2>
            <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.open_period_hint') }}</p>
            <form method="POST" action="{{ route('owner.commissions.open') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.period_label') }} {{ __('messages.optional') }}</label>
                    <input type="text" name="label" value="{{ old('label') }}" placeholder="{{ __('messages.period_label_placeholder') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">📅 {{ __('messages.opening_date') }}</label>
                        <input type="date" name="opened_at" value="{{ old('opened_at', $today) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-3">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">🏦 {{ __('messages.opening_cash') }} {{ __('messages.optional') }}</label>
                        <input type="number" name="opening_cash" step="0.01" min="0" value="{{ old('opening_cash') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-3">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">💰 {{ __('messages.opening_investment') }} {{ __('messages.optional') }}</label>
                    <input type="number" name="investment_amount" step="0.01" min="0.01" value="{{ old('investment_amount') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.note') }} {{ __('messages.optional') }}</label>
                    <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-3">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl py-3">
                    🟢 {{ __('messages.open_period') }}
                </button>
            </form>
        </div>
    @endif

    {{-- 2. Pending payout requests --}}
    <h2 class="font-bold text-lg mb-2">💸 {{ __('messages.payout_requests') }}</h2>
    <div class="space-y-3 mb-6">
        @forelse($requests as $request)
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="font-bold inline-flex items-center gap-1.5"><x-avatar :user="$request->user" size="24" /> {{ $request->user->name }}</div>
                        <div class="text-sm text-gray-500">📅 {{ $request->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="text-xl font-bold text-violet-700 shrink-0">৳{{ number_format((float) $request->amount, 2) }}</div>
                </div>
                <div class="flex gap-2 mt-3">
                    <form method="POST" action="{{ route('owner.commissions.approve', ['request' => $request->id]) }}" class="js-confirm-update">
                        @csrf @method('PATCH')
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg px-4 py-2">
                            ✔️ {{ __('messages.approve_pay') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('owner.commissions.reject', ['request' => $request->id]) }}" class="js-confirm-reject">
                        @csrf @method('PATCH')
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold rounded-lg px-4 py-2">
                            ✖️ {{ __('messages.reject') }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500 mb-6">
                💸 {{ __('messages.no_payout_requests') }}
            </div>
        @endforelse
    </div>

    {{-- 3. Closed periods --}}
    <div class="flex items-center justify-between mb-2">
        <h2 class="font-bold text-lg">📦 {{ __('messages.period_history') }}</h2>
        <a href="{{ route('owner.withdrawals.index') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-lg px-3 py-2">
            🏦 {{ __('messages.withdraw_profit') }}
        </a>
    </div>
    <div class="space-y-3 mb-6">
        @forelse($closedPeriods as $period)
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="font-bold">
                            🔒 {{ $period->label ?: $period->opened_at->format('M Y') }}
                        </div>
                        <div class="text-sm text-gray-500">
                            📅 {{ $period->opened_at->format('d M') }} – {{ $period->closed_at->format('d M Y') }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-bold {{ ($period->profit ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            ৳{{ number_format((float) $period->profit, 2) }}
                        </div>
                        <div class="text-xs text-gray-500">
                            @if(($period->profit ?? 0) >= 0)
                                📈 {{ __('messages.net_profit') }} · 🤝 ×{{ $period->settlements_count }}
                            @else
                                📉 {{ __('messages.period_loss_label') }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500 mb-6">
                📦 {{ __('messages.no_periods') }}
            </div>
        @endforelse
    </div>

    {{-- 4. Settlements list --}}
    <h2 class="font-bold text-lg mb-2">📋 {{ __('messages.commission_settlements') }}</h2>
    <div class="space-y-3">
        @forelse($settlements as $settlement)
            <div class="bg-white rounded-xl shadow p-4 flex items-center justify-between gap-2">
                <div>
                    <div class="font-bold inline-flex items-center gap-1.5"><x-avatar :user="$settlement->user" size="22" /> {{ $settlement->user->name }}</div>
                    <div class="text-sm text-gray-500">
                        🗓️ {{ $settlement->period?->label ?: $settlement->period_start->format('d M').' – '.$settlement->period_end->format('d M Y') }}
                        · 📈 ৳{{ number_format((float) $settlement->business_profit, 2) }}
                        · 🤝 {{ $settlement->commission_rate }}%
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-bold">৳{{ number_format((float) $settlement->amount, 2) }}</div>
                    @if($settlement->status === 'paid')
                        <span class="inline-block text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">✅ {{ __('messages.paid') }}</span>
                    @else
                        <span class="inline-block text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">⏳ {{ __('messages.pending') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                🤝 {{ __('messages.no_commission') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $settlements->links() }}</div>
@endsection
