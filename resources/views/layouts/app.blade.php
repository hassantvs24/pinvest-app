<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — @yield('title', __('messages.dashboard'))</title>
    {{-- Tailwind CSS (CDN) + local jQuery — icon/avatars are offline SVG --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 pb-20 md:pb-6">

    {{-- ===================== Sticky top header ===================== --}}
    <header class="sticky top-0 z-40 bg-emerald-700 text-white shadow">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-lg">
                    <img src="{{ asset('icon.svg') }}" alt="" class="w-8 h-8 rounded-lg shadow">
                    {{ config('app.name') }}
                </a>

                {{-- Desktop navigation --}}
                <nav class="hidden md:flex items-center gap-1 text-sm">
                    <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded {{ request()->routeIs('dashboard') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">🏠 {{ __('messages.home') }}</a>
                    @if(auth()->user()->isOwner())
                        <a href="{{ route('owner.entries.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.entries.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📋 {{ __('messages.entries') }}</a>
                        <a href="{{ route('owner.reports.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.reports.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📊 {{ __('messages.reports') }}</a>
                        <a href="{{ route('owner.expense-heads.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.expense-heads.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">💸 {{ __('messages.expense_heads_page') }}</a>
                        <a href="{{ route('owner.stock.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.stock.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">🏪 {{ __('messages.stock_page') }}</a>
                        <a href="{{ route('owner.productions.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.productions.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">🏭 {{ __('messages.productions') }}</a>
                        <a href="{{ route('owner.masters.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.masters.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">⚙️ {{ __('messages.masters') }}</a>
                        <a href="{{ route('owner.partners.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.partners.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">👥 {{ __('messages.partners') }}</a>
                    @else
                        <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="px-3 py-2 rounded {{ request()->routeIs('entries.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📋 {{ __('messages.my_entries') }}</a>
                        <a href="{{ route('productions.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('productions.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">🏭 {{ __('messages.productions') }}</a>
                        <a href="{{ route('stock-losses.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('stock-losses.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📉 {{ __('messages.stock_losses') }}</a>
                    @endif
                </nav>

                <div class="flex items-center gap-2">
                    {{-- Name + designation click through to the profile page --}}
                    <a href="{{ route('profile') }}" title="{{ __('messages.profile') }}" class="flex items-center gap-2 rounded-lg px-1 py-0.5 hover:bg-emerald-600">
                        <span class="hidden sm:inline-flex items-center gap-1.5 text-sm">
                            <x-avatar :user="auth()->user()" size="28" /> {{ auth()->user()->name }}
                        </span>
                        <span class="text-xs px-2 py-1 rounded-full {{ auth()->user()->isOwner() ? 'bg-amber-400 text-amber-900' : 'bg-emerald-500' }}">
                            {{ auth()->user()->isOwner() ? __('messages.role_owner') : __('messages.role_partner') }}
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="{{ __('messages.logout') }}"
                                class="bg-red-600 hover:bg-red-700 text-white w-9 h-9 rounded-full flex items-center justify-center shadow">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ===================== Flash messages ===================== --}}
    <div class="max-w-6xl mx-auto px-4 mt-3 space-y-2">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 rounded-lg px-4 py-3">✅ {{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3">⏳ {{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3">❌ {{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3">
                ❌ {{ $errors->first() }}
            </div>
        @endif
    </div>

    {{-- ===================== Page content ===================== --}}
    <main class="max-w-6xl mx-auto px-4 mt-4">
        @yield('content')
    </main>

    {{-- ===================== Bottom navigation (mobile only) ===================== --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 shadow-lg">
        <div class="grid {{ auth()->user()->isOwner() ? 'grid-cols-8' : 'grid-cols-4' }} text-center text-xs">
            <a href="{{ route('dashboard') }}" class="py-2 {{ request()->routeIs('dashboard') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                <div class="text-xl">🏠</div>{{ __('messages.home') }}
            </a>
            @if(auth()->user()->isOwner())
                <a href="{{ route('owner.entries.index') }}" class="py-2 {{ request()->routeIs('owner.entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📋</div>{{ __('messages.entries') }}
                </a>
                <a href="{{ route('owner.reports.index') }}" class="py-2 {{ request()->routeIs('owner.reports.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📊</div>{{ __('messages.reports') }}
                </a>
                <a href="{{ route('owner.expense-heads.index') }}" class="py-2 {{ request()->routeIs('owner.expense-heads.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">💸</div>{{ __('messages.expense_heads_page') }}
                </a>
                <a href="{{ route('owner.stock.index') }}" class="py-2 {{ request()->routeIs('owner.stock.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">🏪</div>{{ __('messages.stock_page') }}
                </a>
                <a href="{{ route('owner.productions.index') }}" class="py-2 {{ request()->routeIs('owner.productions.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">🏭</div>{{ __('messages.productions') }}
                </a>
                <a href="{{ route('owner.masters.index') }}" class="py-2 {{ request()->routeIs('owner.masters.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">⚙️</div>{{ __('messages.masters') }}
                </a>
                <a href="{{ route('owner.partners.index') }}" class="py-2 {{ request()->routeIs('owner.partners.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">👥</div>{{ __('messages.partners') }}
                </a>
            @else
                <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="py-2 {{ request()->routeIs('entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📋</div>{{ __('messages.my_entries') }}
                </a>
                <a href="{{ route('productions.index') }}" class="py-2 {{ request()->routeIs('productions.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">🏭</div>{{ __('messages.productions') }}
                </a>
                <a href="{{ route('stock-losses.index') }}" class="py-2 {{ request()->routeIs('stock-losses.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📉</div>{{ __('messages.stock_losses') }}
                </a>
            @endif
        </div>
    </nav>

    {{-- ===================== Shared jQuery behaviors ===================== --}}
    <script>
        $(function () {
            // Live total = quantity x unit price
            function recalcTotal() {
                var qty = parseFloat($('#quantity').val()) || 0;
                var price = parseFloat($('#unit_price').val()) || 0;
                var total = qty * price;
                $('#total_display').val(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
            $(document).on('input', '#quantity, #unit_price', recalcTotal);
            recalcTotal();

            // Item selected: auto-fill sale price + show the item's unit
            $(document).on('change', '#head_id', function () {
                var selected = $(this).find('option:selected');
                var unit = selected.data('unit');
                if (unit !== undefined) {
                    $('#unit_label').text(unit);
                }
                var price = selected.data('price');
                if (price) {
                    $('#unit_price').val(price);
                    recalcTotal();
                }
            });
            $('#head_id').trigger('change');

            // Confirm popup before submitting any entry form
            $(document).on('submit', 'form.js-confirm-submit', function () {
                return confirm(@json(__('messages.submit_confirm')));
            });

            // Confirm popup before any approve/allow action; a data-warning
            // (e.g. cash overdraw) is shown inside the same dialog.
            $(document).on('submit', 'form.js-confirm-approve', function () {
                var message = $(this).data('confirm') || @json(__('messages.approve_confirm'));
                var warning = $(this).data('warning');
                if (warning) {
                    message += '\n\n' + warning;
                }
                return confirm(message);
            });

            // Confirm popup before delete / reject / update
            $(document).on('submit', 'form.js-confirm-delete', function () {
                return confirm(@json(__('messages.confirm_delete')));
            });
            $(document).on('submit', 'form.js-confirm-reject', function () {
                return confirm(@json(__('messages.confirm_reject')));
            });
            $(document).on('submit', 'form.js-confirm-update', function () {
                return confirm(@json(__('messages.confirm_update')));
            });

            // Toggle inline edit forms on entry cards
            $(document).on('click', '.js-edit-toggle', function () {
                $(this).closest('.entry-card').find('.js-edit-form').toggle();
            });

            // Toggle master item rename forms (the row right below the pencil)
            $(document).on('click', '.js-item-edit-toggle', function () {
                $(this).closest('li').next('.js-item-edit-form').toggle();
            });
        });
    </script>
</body>
</html>
