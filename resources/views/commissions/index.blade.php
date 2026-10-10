@extends('layouts.app')

@section('title', __('messages.my_commission'))

@section('content')
    <p class="text-xs text-gray-500 mb-3">💡 {{ __('messages.my_commissions_hint') }}</p>

    {{-- Pending due + request button --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4 border-l-4 border-violet-600">
        <div class="text-sm text-gray-500">🤝 {{ __('messages.commission_due') }}</div>
        <div class="text-3xl font-bold {{ $pendingDue > 0 ? 'text-orange-600' : 'text-green-600' }}">
            {{ \App\Support\Money::format($pendingDue) }}
        </div>
        <div class="text-xs text-gray-500 mt-1">
            {{ __('messages.total') }} {{ __('messages.my_commission') }}: {{ \App\Support\Money::format($earnedTotal) }}
        </div>

        @if($pendingDue > 0)
            @if($hasPendingRequest)
                <div class="mt-3 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3 text-sm">
                    ⏳ {{ __('messages.request_pending_note') }}
                </div>
            @else
                <form method="POST" action="{{ route('commissions.request') }}" class="js-confirm-submit mt-3">
                    @csrf
                    <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold rounded-xl py-3">
                        💸 {{ __('messages.request_payout') }}
                    </button>
                </form>
            @endif
        @endif
    </div>

    {{-- Settlements list --}}
    <h2 class="font-bold text-lg mb-2">🗓️ {{ __('messages.commission_history') }}</h2>
    <div class="space-y-3">
        @forelse($settlements as $settlement)
            <div class="bg-white rounded-xl shadow p-4 flex items-center justify-between gap-2">
                <div>
                    <div class="font-medium">
                        🗓️ {{ $settlement->period?->label ?: $settlement->period_start->format('d M Y').' – '.$settlement->period_end->format('d M Y') }}
                    </div>
                    <div class="text-sm text-gray-500">
                        📈 {{ __('messages.period_profit') }}: {{ \App\Support\Money::format((float) $settlement->business_profit) }}
                        · 🤝 {{ $settlement->commission_rate }}%
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-bold">{{ \App\Support\Money::format((float) $settlement->amount) }}</div>
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
