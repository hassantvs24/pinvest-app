@extends('layouts.app')

@section('title', __('messages.select_language'))

@section('content')
    <div class="flex items-center justify-center py-8">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-6 text-center">
            <div class="text-5xl mb-4">🌐</div>
            <h1 class="text-xl font-bold mb-6">{{ __('messages.select_language') }}</h1>

            <form method="POST" action="{{ route('language.store') }}" class="space-y-3">
                @csrf
                <button type="submit" name="language" value="bn"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-2xl rounded-xl py-4">
                    {{ __('messages.language_bn') }}
                </button>
                <button type="submit" name="language" value="en"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-2xl rounded-xl py-4">
                    {{ __('messages.language_en') }}
                </button>
            </form>
        </div>
    </div>
@endsection
