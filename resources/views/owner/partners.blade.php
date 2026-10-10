@extends('layouts.app')

@section('title', __('messages.partners'))

@section('content')
    {{-- Allow registration (owner-controlled) --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4">
        <h2 class="font-bold mb-1">➕ {{ __('messages.allow_registration') }}</h2>
        <p class="text-xs text-gray-500 mb-3">{{ __('messages.add_allowed_hint') }}</p>

        <form method="POST" action="{{ route('owner.allowances.store') }}" class="flex gap-2 mb-4">
            @csrf
            <input type="text" name="identifier" value="{{ old('identifier') }}" required
                   placeholder="{{ __('messages.identifier_placeholder') }}"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-3 min-w-0">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg px-4 py-3 shrink-0">
                💾 {{ __('messages.save') }}
            </button>
        </form>

        @if($allowances->isNotEmpty())
            <div class="text-xs font-medium text-gray-500 mb-1">📋 {{ __('messages.allowed_list') }}</div>
            <ul class="divide-y divide-gray-100">
                @foreach($allowances as $allowance)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <div class="text-sm min-w-0 truncate">
                            {{ $allowance->phone ? '📱' : '✉️' }} {{ $allowance->displayValue() }}
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs px-2 py-1 rounded-full {{ $allowance->used_at ? 'bg-gray-200 text-gray-500' : 'bg-green-100 text-green-700' }}">
                                {{ $allowance->used_at ? '✅ '.__('messages.used') : '⏳ '.__('messages.unused') }}
                            </span>
                            <form method="POST" action="{{ route('owner.allowances.destroy', ['allowance' => $allowance->id]) }}" class="js-confirm-delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 text-lg" title="{{ __('messages.delete') }}">🗑️</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <a href="{{ route('owner.partners.create') }}"
       class="block w-full bg-emerald-700 hover:bg-emerald-800 text-white text-center font-bold rounded-xl py-3 mb-4">
        ➕ {{ __('messages.new_partner') }}
    </a>

    <div class="space-y-3">
        @forelse($partners as $partner)
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold text-lg {{ $partner->is_active ? '' : 'line-through text-gray-400' }}">
                            <span class="inline-flex items-center gap-1.5"><x-avatar :user="$partner" size="22" /> {{ $partner->name }}</span>
                        </div>
                        <div class="text-sm text-gray-500">📱 {{ $partner->phone }}</div>
                        @if($partner->email)
                            <div class="text-sm text-gray-500">✉️ {{ $partner->email }}</div>
                        @endif
                        <div class="text-sm text-violet-600">🤝 {{ $partner->commission_rate }}%</div>
                        @if($partner->pending_sales_count > 0)
                            <div class="text-sm text-yellow-700">⏳ {{ $partner->pending_sales_count }}</div>
                        @endif
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full {{ $partner->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500' }}">
                        {{ $partner->is_active ? __('messages.active') : __('messages.inactive') }}
                    </span>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="{{ route('owner.partners.edit', ['partner' => $partner->id]) }}"
                       class="bg-gray-200 hover:bg-gray-300 text-sm font-bold rounded-lg px-3 py-2">✏️ {{ __('messages.edit') }}</a>
                    <form method="POST" action="{{ route('owner.partners.destroy', ['partner' => $partner->id]) }}" class="js-confirm-delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="bg-red-100 text-red-700 hover:bg-red-200 text-sm font-bold rounded-lg px-3 py-2">
                            🗑️ {{ __('messages.delete') }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                👥 {{ __('messages.no_partners') }}
            </div>
        @endforelse
    </div>
@endsection
