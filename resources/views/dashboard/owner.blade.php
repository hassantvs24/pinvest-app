@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
    {{-- Quick actions (right under the nav) --}}
    <div class="grid grid-cols-3 gap-2">
        <a href="{{ route('entries.create', ['type' => 'sales']) }}" class="bg-blue-600 hover:bg-blue-700 text-white text-center font-bold rounded-xl py-3 text-sm">
            💵 {{ __('messages.add_sale') }}
        </a>
        <a href="{{ route('entries.create', ['type' => 'purchases']) }}" class="bg-orange-500 hover:bg-orange-600 text-white text-center font-bold rounded-xl py-3 text-sm">
            🛒 {{ __('messages.add_purchase') }}
        </a>
        <a href="{{ route('entries.create', ['type' => 'expenses']) }}" class="bg-rose-500 hover:bg-rose-600 text-white text-center font-bold rounded-xl py-3 text-sm">
            💸 {{ __('messages.add_expense') }}
        </a>
    </div>
    <div class="grid grid-cols-4 gap-2 mt-2">
        <a href="{{ route('owner.investments.index') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-center font-bold rounded-xl py-3 text-xs">
            ➕ {{ __('messages.invest') }}
        </a>
        <a href="{{ route('owner.commissions.index') }}" class="bg-violet-600 hover:bg-violet-700 text-white text-center font-bold rounded-xl py-3 text-xs">
            💸 {{ __('messages.payout_requests') }}
        </a>
        <a href="{{ route('owner.withdrawals.index') }}" class="bg-amber-600 hover:bg-amber-700 text-white text-center font-bold rounded-xl py-3 text-xs">
            🏦 {{ __('messages.withdraw_profit') }}
        </a>
        <a href="{{ route('owner.reports.index') }}" class="bg-slate-600 hover:bg-slate-700 text-white text-center font-bold rounded-xl py-3 text-xs">
            📊 {{ __('messages.reports') }}
        </a>
    </div>

    {{-- Cycle status card --}}
    @if($openPeriod)
        <a href="{{ route('owner.commissions.close_preview') }}" class="block bg-emerald-50 border border-emerald-300 rounded-xl shadow p-4 mt-3">
            <div class="flex items-center justify-between gap-2 mb-1">
                <h2 class="font-bold text-emerald-800">🟢 {{ __('messages.current_period') }}{{ $openPeriod->label ? ' — '.$openPeriod->label : '' }}</h2>
                <span class="text-xs px-2 py-1 rounded-full bg-emerald-200 text-emerald-800">
                    {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                </span>
            </div>
            <div class="text-sm text-gray-600">
                📅 {{ __('messages.opened_on') }}: {{ $openPeriod->opened_at->format('d M Y') }}
                @if($openPeriod->opening_cash !== null)
                    · 🏦 {{ __('messages.opening_cash') }}: ৳{{ number_format((float) $openPeriod->opening_cash, 2) }}
                @endif
            </div>
            <div class="text-sm font-bold text-emerald-700 mt-1">🔒 {{ __('messages.click_to_close') }}</div>
        </a>
    @else
        <div class="bg-yellow-100 border border-yellow-400 rounded-xl shadow p-4 mt-3 flex items-center justify-between gap-2">
            <div>
                <div class="font-bold text-yellow-800">🔴 {{ __('messages.no_open_period') }}</div>
                <div class="text-sm text-yellow-700">{{ __('messages.entries_blocked') }}</div>
            </div>
            <a href="{{ route('owner.commissions.index') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg px-4 py-2 text-sm shrink-0">
                🟢 {{ __('messages.open_period_now') }}
            </a>
        </div>
    @endif

    {{-- Pending confirmations alert --}}
    @php $totalPending = $pending['expenses'] + $pending['purchases'] + $pending['sales'] + $pending['productions']; @endphp
    @if($totalPending > 0)
        <a href="{{ route('owner.entries.index', ['status' => 'pending']) }}"
           class="block bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3 mt-3">
            ⏳ {{ __('messages.pending_confirmations') }} —
            💸 {{ $pending['expenses'] }} · 🛒 {{ $pending['purchases'] }} · 💰 {{ $pending['sales'] }} · 🏭 {{ $pending['productions'] }}
        </a>
    @endif
    @if($pendingPayoutRequests > 0)
        <a href="{{ route('owner.commissions.index') }}"
           class="block bg-orange-100 border border-orange-400 text-orange-800 rounded-lg px-4 py-3 mt-3">
            💸 {{ trans_choice(__('messages.pending_payout_requests'), $pendingPayoutRequests, ['count' => $pendingPayoutRequests]) }}
        </a>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-700">
            <div class="text-sm text-gray-500">💰 {{ __('messages.total_investment') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['investment'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
            <div class="text-sm text-gray-500">🛒 {{ __('messages.total_purchase') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['purchase'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-rose-500">
            <div class="text-sm text-gray-500">💸 {{ __('messages.total_expense') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['expense'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-600">
            <div class="text-sm text-gray-500">💵 {{ __('messages.total_sales') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['sales'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-violet-600">
            <div class="text-sm text-gray-500">
                🤝 {{ __('messages.total_commission') }}{{ $commissionEstimated ? ' '.__('messages.estimated_hint') : '' }}
            </div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['commission'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-slate-600">
            <div class="text-sm text-gray-500">💳 {{ __('messages.total_payout') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['payout'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $stats['net_profit'] >= 0 ? 'border-green-500' : 'border-red-500' }}">
            <div class="text-sm text-gray-500">📈 {{ __('messages.net_profit') }}</div>
            <div class="text-2xl font-bold {{ $stats['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                ৳{{ number_format($stats['net_profit'], 2) }}
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-teal-600">
            <div class="text-sm text-gray-500">🏪 {{ __('messages.stock_value') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['stock_value'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-indigo-600">
            <div class="text-sm text-gray-500">🏷️ {{ __('messages.cost_of_goods_sold') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['cogs'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-700">
            <div class="text-sm text-gray-500">🏦 {{ __('messages.cash_in_hand') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($cashInHand, 2) }}</div>
            <p class="text-xs text-gray-500 mt-1">{{ __('messages.cash_pool_hint') }}</p>
            <a href="{{ route('owner.investments.index') }}"
               class="inline-block mt-2 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg px-3 py-1.5">
                ➕ {{ __('messages.add_cash') }}
            </a>
        </div>
    </div>

    {{-- Partner leaderboard --}}
    <div class="bg-white rounded-xl shadow mt-4 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 font-bold">👥 {{ __('messages.leaderboard') }}</div>
        @if($leaderboard->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">👥 {{ __('messages.no_partners') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($leaderboard as $partner)
                    <li class="px-4 py-3 {{ $partner['is_active'] ? '' : 'bg-gray-50 opacity-60' }}">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="font-medium truncate inline-flex items-center gap-1.5 {{ $partner['is_active'] ? '' : 'line-through text-gray-400' }}">
                                <x-avatar :user="$partner['user']" size="24" /> {{ $partner['name'] }}
                                @unless($partner['is_active'])
                                    <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-500 no-underline" style="text-decoration:none">⏸️ {{ __('messages.inactive') }}</span>
                                @endunless
                            </div>
                            <div class="text-xl font-bold text-violet-600 shrink-0">{{ $partner['commission_rate'] }}%</div>
                        </div>
                        <div class="text-xs text-gray-500">
                            💵 {{ __('messages.total_sales') }} ৳{{ number_format($partner['total_sales'], 2) }}
                            · 🛒 {{ __('messages.total_purchase') }} ৳{{ number_format($partner['total_purchase'], 2) }}
                            · 💸 {{ __('messages.total_expense') }} ৳{{ number_format($partner['total_expense'], 2) }}
                            · 🤝 {{ __('messages.total_commission') }}{{ $commissionEstimated ? ' '.__('messages.estimated_hint') : '' }}:
                            ৳{{ number_format($partner['commission'], 2) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
