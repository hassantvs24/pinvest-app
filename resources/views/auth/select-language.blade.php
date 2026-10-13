@extends('layouts.app')

@section('title', __('messages.select_language'))

@section('content')
    <div class="flex items-center justify-center py-8">
        <div class="w-full max-w-md card p-6 sm:p-8 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-brand-gradient shadow-lg shadow-emerald-600/30 text-white flex items-center justify-center">
                <x-icon name="globe-alt" class="w-7 h-7" />
            </div>
            <h1 class="text-xl font-extrabold text-gray-900 mb-6">{{ __('messages.select_language') }}</h1>

            <form method="POST" action="{{ route('language.store') }}" class="space-y-3">
                @csrf
                <button type="submit" name="language" value="bn" class="btn-primary w-full text-lg">
                    {{ __('messages.language_bn') }}
                </button>
                <button type="submit" name="language" value="en" class="btn-sale w-full text-lg">
                    {{ __('messages.language_en') }}
                </button>
            </form>
        </div>
    </div>
@endsection
