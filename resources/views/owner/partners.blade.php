@extends('layouts.app')

@section('title', __('messages.partners'))

@section('content')
    {{-- Allow registration (owner-controlled) --}}
    <div class="card mb-4">
        <h2 class="font-bold mb-1 flex items-center gap-2">
            <x-icon name="plus-circle" class="w-5 h-5 text-emerald-600" /> {{ __('messages.allow_registration') }}
        </h2>
        <p class="text-xs text-gray-500 mb-3">{{ __('messages.add_allowed_hint') }}</p>

        <form method="POST" action="{{ route('owner.allowances.store') }}" class="flex gap-2 mb-4">
            @csrf
            <input type="text" name="identifier" value="{{ old('identifier') }}" required
                   placeholder="{{ __('messages.identifier_placeholder') }}"
                   class="flex-1 input min-w-0">
            <button type="submit" class="btn-primary shrink-0">
                <x-icon name="check" class="w-5 h-5" /> {{ __('messages.save') }}
            </button>
        </form>

        @if($allowances->isNotEmpty())
            <div class="text-xs font-medium text-gray-500 mb-1 flex items-center gap-1.5">
                <x-icon name="clipboard-document-list" class="w-4 h-4" /> {{ __('messages.allowed_list') }}
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($allowances as $allowance)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <div class="text-sm min-w-0 truncate flex items-center gap-1.5">
                            <x-icon :name="$allowance->phone ? 'phone' : 'envelope'" class="w-4 h-4 text-gray-400" /> {{ $allowance->displayValue() }}
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="badge {{ $allowance->used_at ? 'badge-inactive' : 'badge-confirmed' }}">
                                <x-icon :name="$allowance->used_at ? 'check' : 'clock'" class="w-3.5 h-3.5" /> {{ $allowance->used_at ? __('messages.used') : __('messages.unused') }}
                            </span>
                            <form method="POST" action="{{ route('owner.allowances.destroy', ['allowance' => $allowance->id]) }}" class="js-confirm-delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon w-9 h-9 text-red-500 hover:bg-red-50" title="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-5 h-5" />
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <a href="{{ route('owner.partners.create') }}" class="btn-primary w-full mb-4">
        <x-icon name="plus" class="w-5 h-5" /> {{ __('messages.new_partner') }}
    </a>

    <div class="space-y-3">
        @forelse($partners as $partner)
            <div class="card">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold text-lg {{ $partner->is_active ? '' : 'line-through text-gray-400' }}">
                            <span class="inline-flex items-center gap-1.5"><x-avatar :user="$partner" size="22" /> {{ $partner->name }}</span>
                        </div>
                        <div class="text-sm text-gray-500 flex items-center gap-1.5"><x-icon name="phone" class="w-4 h-4" /> {{ $partner->phone }}</div>
                        @if($partner->email)
                            <div class="text-sm text-gray-500 flex items-center gap-1.5"><x-icon name="envelope" class="w-4 h-4" /> {{ $partner->email }}</div>
                        @endif
                        <div class="text-sm text-violet-600 flex items-center gap-1.5"><x-icon name="receipt-percent" class="w-4 h-4" /> {{ $partner->commission_rate }}%</div>
                        @if($partner->pending_sales_count > 0)
                            <div class="text-sm text-amber-700 flex items-center gap-1.5"><x-icon name="clock" class="w-4 h-4" /> {{ $partner->pending_sales_count }}</div>
                        @endif
                    </div>
                    <span class="badge {{ $partner->is_active ? 'badge-confirmed' : 'badge-inactive' }}">
                        {{ $partner->is_active ? __('messages.active') : __('messages.inactive') }}
                    </span>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="{{ route('owner.partners.edit', ['partner' => $partner->id]) }}"
                       class="btn-neutral text-sm">
                        <x-icon name="pencil" class="w-4 h-4" /> {{ __('messages.edit') }}
                    </a>
                    <form method="POST" action="{{ route('owner.partners.destroy', ['partner' => $partner->id]) }}" class="js-confirm-delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-soft-danger text-sm">
                            <x-icon name="trash" class="w-4 h-4" /> {{ __('messages.delete') }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <x-icon name="user-group" class="w-8 h-8 mx-auto mb-2 text-gray-300" /> {{ __('messages.no_partners') }}
            </div>
        @endforelse
    </div>
@endsection
