@extends('layouts.app')

@section('title', '403')

@section('content')
    <div class="flex items-center justify-center py-12">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="text-6xl mb-4">⛔</div>
            <p class="text-lg text-gray-700 mb-6">{{ __('messages.forbidden') }}</p>
            <a href="{{ route('dashboard') }}" class="inline-block bg-emerald-700 text-white font-bold rounded-lg px-6 py-3">
                🏠 {{ __('messages.back_home') }}
            </a>
        </div>
    </div>
@endsection
