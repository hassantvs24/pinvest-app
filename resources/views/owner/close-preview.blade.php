@extends('layouts.app')

@section('title', __('messages.close_summary'))

@section('content')
    <div class="max-w-xl mx-auto">
        <a href="{{ route('owner.commissions.index') }}" class="inline-block text-sm text-gray-600 mb-3">⬅️ {{ __('messages.back') }}</a>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h1 class="text-xl font-bold mb-1">🔒 {{ __('messages.close_summary') }}</h1>
            <p class="text-sm text-gray-500 mb-4">
                {{ $period->label ?: $period->opened_at->format('M Y') }} —
                📅 {{ $period->opened_at->format('d M Y') }} → {{ $closedAt->format('d M Y') }}
            </p>

            {{-- Profit --}}
            <div class="rounded-xl p-4 mb-4 {{ $profit >= 0 ? 'bg-green-50 border border-green-300' : 'bg-red-50 border border-red-300' }}">
                <div class="text-sm text-gray-500">📈 {{ __('messages.net_profit') }}</div>
                <div class="text-3xl font-bold {{ $profit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    ৳{{ number_format($profit, 2) }}
                </div>
            </div>

            @if($profit > 0 && count($rows) > 0)
                {{-- Per-partner expected commission --}}
                <h2 class="font-bold mb-2">🤝 {{ __('messages.expected_commission') }}</h2>
                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 mb-4">
                    @foreach($rows as $row)
                        <li class="px-4 py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-medium">👤 {{ $row['user']->name }}</span>
                                <span class="text-xs text-violet-600">🤝 {{ $row['rate'] }}%</span>
                            </div>
                            <div class="font-bold">৳{{ number_format($row['amount'], 2) }}</div>
                        </li>
                    @endforeach
                    <li class="px-4 py-2 flex items-center justify-between text-sm bg-violet-50 font-bold">
                        <div>🤝 {{ __('messages.total_commission') }}</div>
                        <div>৳{{ number_format($totalCommission, 2) }}</div>
                    </li>
                    <li class="px-4 py-2 flex items-center justify-between text-sm bg-emerald-50 font-bold">
                        <div>👔 {{ __('messages.owner_share') }}</div>
                        <div class="{{ $ownerShare >= 0 ? 'text-emerald-700' : 'text-red-700' }}">৳{{ number_format($ownerShare, 2) }}</div>
                    </li>
                </ul>
            @elseif($profit <= 0)
                <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">
                    📉 {{ __('messages.loss_no_commission') }}
                </div>
            @endif

            {{-- Confirm --}}
            <form method="POST" action="{{ route('owner.commissions.close') }}" class="js-confirm-update">
                @csrf
                <input type="hidden" name="closed_at" value="{{ $closedAt->format('Y-m-d') }}">
                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl py-3">
                    ✅ {{ __('messages.confirm_close') }}
                </button>
            </form>
        </div>
    </div>
@endsection
