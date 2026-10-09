@extends('layouts.app')

@section('title', __('messages.reports'))

@section('content')
    {{-- Date range filter + presets --}}
    <form method="GET" action="{{ route('owner.reports.index') }}" class="bg-white rounded-xl shadow p-4 mb-4 print:hidden">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">📅 {{ __('messages.from_date') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="w-full border border-gray-300 rounded-lg px-3 py-3">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">📅 {{ __('messages.to_date') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="w-full border border-gray-300 rounded-lg px-3 py-3">
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg px-5 py-3">
                🔍 {{ __('messages.filter') }}
            </button>
            <a href="{{ route('owner.reports.index', ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" class="bg-gray-200 hover:bg-gray-300 font-bold rounded-lg px-4 py-3 text-sm">{{ __('messages.this_month') }}</a>
            <a href="{{ route('owner.reports.index', ['from' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'to' => now()->subMonth()->endOfMonth()->format('Y-m-d')]) }}" class="bg-gray-200 hover:bg-gray-300 font-bold rounded-lg px-4 py-3 text-sm">{{ __('messages.last_month') }}</a>
            <a href="{{ route('owner.reports.index') }}" class="bg-gray-200 hover:bg-gray-300 font-bold rounded-lg px-4 py-3 text-sm">{{ __('messages.all_time') }}</a>
            <button type="button" onclick="window.print()" class="bg-slate-600 hover:bg-slate-700 text-white font-bold rounded-lg px-4 py-3 text-sm">
                🖨️ {{ __('messages.print') }}
            </button>
        </div>
    </form>

    {{-- 1. Business summary for the period --}}
    <h2 class="font-bold text-lg mb-2">📊 {{ __('messages.business_summary') }}
        @if($from || $to)
            <span class="text-sm font-normal text-gray-500">({{ $from ?? '…' }} — {{ $to ?? '…' }})</span>
        @endif
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
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
            <div class="text-sm text-gray-500">🏦 {{ __('messages.cash_in_hand') }} ({{ __('messages.all_time') }})</div>
            <div class="text-2xl font-bold">৳{{ number_format($lifetimeCashInHand, 2) }}</div>
        </div>
    </div>

    {{-- 2. Investment report --}}
    <h2 class="font-bold text-lg mb-2">💰 {{ __('messages.investment_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">💰 {{ __('messages.total_investment') }}</span>
            <span class="font-bold">৳{{ number_format($stats['investment'], 2) }}</span>
        </div>
        @if($investments->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">💰 {{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($investments as $investment)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $investment->note ?? '—' }}</div>
                            <div class="text-gray-500">📅 {{ $investment->invested_at->format('d M Y') }}</div>
                        </div>
                        <div class="font-bold shrink-0">৳{{ number_format((float) $investment->amount, 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 3. Sales report --}}
    <h2 class="font-bold text-lg mb-2">💵 {{ __('messages.sales_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">💵 {{ __('messages.total_sales') }}</span>
            <span class="font-bold">৳{{ number_format($stats['sales'], 2) }}</span>
        </div>
        @if($salesByItem->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">💵 {{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($salesByItem as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div>{{ $row['name'] }} <span class="text-gray-500">{{ $row['quantity'] }} {{ $row['unit'] }}</span></div>
                        <div class="font-bold shrink-0">৳{{ number_format($row['total'], 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 4. Purchase report --}}
    <h2 class="font-bold text-lg mb-2">🛒 {{ __('messages.purchase_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">🛒 {{ __('messages.total_purchase') }}</span>
            <span class="font-bold">৳{{ number_format($stats['purchase'], 2) }}</span>
        </div>
        @if($purchasesByItem->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">🛒 {{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($purchasesByItem as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div>{{ $row['name'] }} <span class="text-gray-500">{{ $row['quantity'] }} {{ $row['unit'] }}</span></div>
                        <div class="font-bold shrink-0">৳{{ number_format($row['total'], 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 5. Expense report --}}
    <h2 class="font-bold text-lg mb-2">💸 {{ __('messages.expense_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">💸 {{ __('messages.total_expense') }}</span>
            <span class="font-bold">৳{{ number_format($stats['expense'], 2) }}</span>
        </div>
        @if($expensesByHead->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">💸 {{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($expensesByHead as $row)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div>{{ $row['name'] }}</div>
                        <div class="font-bold shrink-0">৳{{ number_format($row['total'], 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 6. Partner & commission report --}}
    <h2 class="font-bold text-lg mb-2">🤝 {{ __('messages.partner_commission_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        @if($partners->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">👥 {{ __('messages.no_partners') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($partners as $partner)
                    <li class="px-4 py-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-bold">👤 {{ $partner['name'] }}</div>
                            <div class="text-xs text-violet-600 font-bold">🤝 {{ $partner['commission_rate'] }}%</div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div><span class="text-gray-500">💵</span> ৳{{ number_format($partner['sales'], 2) }}</div>
                            <div><span class="text-gray-500">🤝</span> ৳{{ number_format($partner['commission'], 2) }}</div>
                            <div><span class="text-gray-500">🛒</span> ৳{{ number_format($partner['purchase'], 2) }}</div>
                            <div><span class="text-gray-500">💸</span> ৳{{ number_format($partner['expense'], 2) }}</div>
                            <div><span class="text-gray-500">💳</span> ৳{{ number_format($partner['payout'], 2) }}</div>
                            <div class="font-bold {{ $partner['commission_due'] > 0 ? 'text-orange-600' : 'text-green-600' }}">
                                ⏳ ৳{{ number_format($partner['commission_due'], 2) }}
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-4 py-2 bg-gray-50 text-xs text-gray-500 border-t border-gray-100">
                💵 {{ __('messages.total_sales') }} · 🤝 {{ __('messages.commission_earned') }} · 🛒 {{ __('messages.total_purchase') }} ·
                💸 {{ __('messages.total_expense') }} · 💳 {{ __('messages.commission_paid') }} · ⏳ {{ __('messages.commission_due') }}
            </div>
        @endif
    </div>

    {{-- 7. Commission settlements report --}}
    <h2 class="font-bold text-lg mb-2">🤝 {{ __('messages.commission_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">🤝 {{ __('messages.total_commission') }}</span>
            <span class="font-bold">৳{{ number_format($stats['commission'], 2) }}</span>
        </div>
        @if($settlements->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">🤝 {{ __('messages.no_commission') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($settlements as $settlement)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium">👤 {{ $settlement->user->name }}</div>
                            <div class="text-gray-500">
                                📅 {{ $settlement->period_start->format('d M') }} – {{ $settlement->period_end->format('d M Y') }}
                                · 📈 ৳{{ number_format((float) $settlement->business_profit, 2) }}
                                · {{ $settlement->commission_rate }}%
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold">৳{{ number_format((float) $settlement->amount, 2) }}</div>
                            @if($settlement->status === 'paid')
                                <span class="text-xs text-green-700">✅ {{ __('messages.paid') }}</span>
                            @else
                                <span class="text-xs text-yellow-700">⏳ {{ __('messages.pending') }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 8. Owner withdrawal report --}}
    <h2 class="font-bold text-lg mb-2">🏦 {{ __('messages.withdrawal_report') }}</h2>
    <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold">🏦 {{ __('messages.total_withdrawn') }}</span>
            <span class="font-bold">৳{{ number_format($stats['withdrawal'], 2) }}</span>
        </div>
        @if($withdrawals->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">🏦 {{ __('messages.no_entries') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($withdrawals as $withdrawal)
                    <li class="px-4 py-2 flex items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $withdrawal->note ?? '—' }}</div>
                            <div class="text-gray-500">📅 {{ $withdrawal->withdrawn_at->format('d M Y') }}</div>
                        </div>
                        <div class="font-bold shrink-0">৳{{ number_format((float) $withdrawal->amount, 2) }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 9. Monthly breakdown (last 6 months) --}}
    <h2 class="font-bold text-lg mb-2">🗓️ {{ __('messages.monthly_report') }}</h2>
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <ul class="divide-y divide-gray-100">
            @foreach($months as $month)
                <li class="px-4 py-3 flex items-center justify-between gap-2">
                    <div class="font-medium">{{ $month['label'] }}</div>
                    <div class="text-right">
                        <div class="font-bold">৳{{ number_format($month['sales'], 2) }}</div>
                        <div class="text-xs {{ $month['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            📈 {{ __('messages.net_profit') }}: ৳{{ number_format($month['net_profit'], 2) }}
                        </div>
                        <div class="text-xs {{ $month['owner_share'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                            👔 {{ __('messages.owner_share') }}: ৳{{ number_format($month['owner_share'], 2) }}
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <style>
        @media print {
            header, nav, .print\:hidden { display: none !important; }
            body { background: white; }
        }
    </style>
@endsection
