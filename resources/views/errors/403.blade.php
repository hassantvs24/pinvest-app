@extends('layouts.app')

@section('title', '403')

@section('content')
    <div class="flex items-center justify-center py-12">
        <div class="w-full max-w-md card p-8 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 shadow-lg shadow-red-500/30 text-white flex items-center justify-center">
                <x-icon name="shield-exclamation" class="w-8 h-8" />
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900 mb-1">403</h1>
            <p class="text-lg text-gray-600 mb-6">{{ __('messages.forbidden') }}</p>
            <a href="{{ route('dashboard') }}" class="btn-primary inline-flex">
                <x-icon name="home" class="w-5 h-5" /> {{ __('messages.back_home') }}
            </a>
        </div>
    </div>
@endsection
