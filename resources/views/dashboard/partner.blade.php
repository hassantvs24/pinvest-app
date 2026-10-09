@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
    {{-- Pending alert --}}
    @if($pendingCount > 0)
        <a href="{{ route('entries.index', ['type' => 'sales', 'status' => 'pending']) }}"
           class="block bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3 mb-4">
            ⏳ {{ __('messages.pending_entries') }}: {{ $pendingCount }}
        </a>
    @endif

    {{-- My summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-600">
            <div class="text-sm text-gray-500">💵 {{ __('messages.my_sales') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['sales'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-violet-600">
            <div class="text-sm text-gray-500">🤝 {{ __('messages.my_commission') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['commission'], 2) }}</div>
            <div class="text-xs text-gray-500">{{ $commissionRate }}% · ⏳ {{ __('messages.commission_due') }}:
                <a href="{{ route('commissions.index') }}" class="font-bold text-orange-600 underline">
                    ৳{{ number_format($pendingCommission, 2) }}
                </a>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
            <div class="text-sm text-gray-500">🛒 {{ __('messages.my_purchase') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['purchase'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-rose-500">
            <div class="text-sm text-gray-500">💸 {{ __('messages.my_expense') }}</div>
            <div class="text-2xl font-bold">৳{{ number_format($stats['expense'], 2) }}</div>
        </div>
    </div>

    {{-- Quick add buttons --}}
    <div class="mt-4">
        <div class="text-sm font-medium text-gray-600 mb-2">➕ {{ __('messages.quick_add') }}</div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="{{ route('entries.create', ['type' => 'sales']) }}" class="bg-blue-600 hover:bg-blue-700 text-white text-center font-bold rounded-xl py-3">
                💵 {{ __('messages.add_sale') }}
            </a>
            <a href="{{ route('entries.create', ['type' => 'purchases']) }}" class="bg-orange-500 hover:bg-orange-600 text-white text-center font-bold rounded-xl py-3">
                🛒 {{ __('messages.add_purchase') }}
            </a>
            <a href="{{ route('entries.create', ['type' => 'expenses']) }}" class="bg-rose-500 hover:bg-rose-600 text-white text-center font-bold rounded-xl py-3">
                💸 {{ __('messages.add_expense') }}
            </a>
        </div>
    </div>
@endsection
