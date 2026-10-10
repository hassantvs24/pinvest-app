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

    {{-- Cycle status (read-only) --}}
    @if($openPeriod)
        <div class="bg-emerald-50 border border-emerald-300 rounded-xl shadow p-4 mt-3">
            <div class="flex items-center justify-between gap-2 mb-1">
                <h2 class="font-bold text-emerald-800">🟢 {{ __('messages.current_period') }}{{ $openPeriod->label ? ' — '.$openPeriod->label : '' }}</h2>
                <span class="text-xs px-2 py-1 rounded-full bg-emerald-200 text-emerald-800">
                    {{ trans_choice(__('messages.period_days'), $openDays, ['count' => $openDays]) }}
                </span>
            </div>
            <div class="text-sm text-gray-600">
                📅 {{ __('messages.opened_on') }}: {{ $openPeriod->opened_at->format('d M Y') }}
            </div>
            <div class="text-xs text-gray-500 mt-1">💡 {{ __('messages.partner_period_note') }}</div>
        </div>
    @else
        <div class="bg-yellow-100 border border-yellow-400 rounded-xl shadow p-4 mt-3">
            <div class="font-bold text-yellow-800">🔴 {{ __('messages.no_open_period') }}</div>
            <div class="text-sm text-yellow-700">{{ __('messages.partner_no_period_wait') }}</div>
        </div>
    @endif

    {{-- Pending alert --}}
    @if($pendingCount > 0)
        <a href="{{ route('entries.index', ['type' => 'sales', 'status' => 'pending']) }}"
           class="block bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3 mt-3">
            ⏳ {{ __('messages.pending_entries') }}: {{ $pendingCount }}
        </a>
    @endif

    {{-- My summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-600">
            <div class="text-sm text-gray-500">💵 {{ __('messages.my_sales') }}</div>
            <div class="text-2xl font-bold">{{ \App\Support\Money::format($stats['sales']) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-violet-600">
            <div class="text-sm text-gray-500">
                🤝 {{ __('messages.my_commission') }}{{ $commissionEstimated ? ' '.__('messages.estimated_hint') : '' }}
            </div>
            <div class="text-2xl font-bold">{{ \App\Support\Money::format($stats['commission']) }}</div>
            <div class="text-xs text-gray-500">{{ $commissionRate }}% · ⏳ {{ __('messages.commission_due') }}:
                <a href="{{ route('commissions.index') }}" class="font-bold text-orange-600 underline">
                    {{ \App\Support\Money::format($pendingCommission) }}
                </a>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
            <div class="text-sm text-gray-500">🛒 {{ __('messages.my_purchase') }}</div>
            <div class="text-2xl font-bold">{{ \App\Support\Money::format($stats['purchase']) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-rose-500">
            <div class="text-sm text-gray-500">💸 {{ __('messages.my_expense') }}</div>
            <div class="text-2xl font-bold">{{ \App\Support\Money::format($stats['expense']) }}</div>
        </div>
    </div>

    {{-- Last cycle commission --}}
    @if($lastSettlement)
        <a href="{{ route('commissions.index') }}" class="block bg-white rounded-xl shadow p-4 mt-3 border-l-4 border-violet-600">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="text-sm text-gray-500">🗓️ {{ __('messages.last_cycle_commission') }}</div>
                    <div class="font-bold">
                        {{ $lastSettlement->period?->label ?: $lastSettlement->period_start->format('M Y') }}
                        · {{ $lastSettlement->period_start->format('d M') }} – {{ $lastSettlement->period_end->format('d M Y') }}
                    </div>
                    <div class="text-xs text-gray-500">
                        📈 {{ __('messages.period_profit') }}: {{ \App\Support\Money::format((float) $lastSettlement->business_profit) }}
                        · 🤝 {{ $lastSettlement->commission_rate }}%
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xl font-bold">{{ \App\Support\Money::format((float) $lastSettlement->amount) }}</div>
                    @if($lastSettlement->status === 'paid')
                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">✅ {{ __('messages.paid') }}</span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">⏳ {{ __('messages.pending') }}</span>
                    @endif
                </div>
            </div>
        </a>
    @endif
@endsection
