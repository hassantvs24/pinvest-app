@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
    {{-- Pending confirmations alert --}}
    @php $totalPending = $pending['expenses'] + $pending['purchases'] + $pending['sales']; @endphp
    @if($totalPending > 0)
        <a href="{{ route('owner.entries.index', ['status' => 'pending']) }}"
           class="block bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3 mb-4">
            ⏳ {{ __('messages.pending_confirmations') }} —
            💸 {{ $pending['expenses'] }} · 🛒 {{ $pending['purchases'] }} · 💰 {{ $pending['sales'] }}
        </a>
    @endif
    @if($pendingPayoutRequests > 0)
        <a href="{{ route('owner.commissions.index') }}"
           class="block bg-orange-100 border border-orange-400 text-orange-800 rounded-lg px-4 py-3 mb-4">
            💸 {{ trans_choice(__('messages.pending_payout_requests'), $pendingPayoutRequests, ['count' => $pendingPayoutRequests]) }}
        </a>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
            <div class="text-sm text-gray-500">🤝 {{ __('messages.total_commission') }}</div>
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
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-700">
            <div class="text-sm text-gray-500">🏦 {{ __('messages.cash_in_hand') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['cash_in_hand'], 2) }}</div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 gap-3 mt-4">
        <a href="{{ route('owner.investments.index') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-center font-bold rounded-xl py-4">
            ➕ {{ __('messages.invest') }}
        </a>
        <a href="{{ route('owner.commissions.index') }}" class="bg-violet-600 hover:bg-violet-700 text-white text-center font-bold rounded-xl py-4">
            💸 {{ __('messages.payout_requests') }}
        </a>
        <a href="{{ route('owner.withdrawals.index') }}" class="bg-amber-600 hover:bg-amber-700 text-white text-center font-bold rounded-xl py-4">
            🏦 {{ __('messages.withdraw_profit') }}
        </a>
        <a href="{{ route('owner.reports.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-center font-bold rounded-xl py-4">
            📊 {{ __('messages.reports') }}
        </a>
    </div>

    {{-- Partner leaderboard --}}
    <div class="bg-white rounded-xl shadow mt-4 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 font-bold">👥 {{ __('messages.leaderboard') }}</div>
        @if($leaderboard->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">👥 {{ __('messages.no_partners') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($leaderboard as $partner)
                    <li class="px-4 py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $partner['name'] }}</div>
                            <div class="text-xs text-violet-600">🤝 {{ $partner['commission_rate'] }}%</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">৳{{ number_format($partner['total_sales'], 2) }}</div>
                            <div class="text-xs text-gray-500">💵 {{ __('messages.total_sales') }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
