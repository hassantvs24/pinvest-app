<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ config('app.name') }} — @yield('title', __('messages.dashboard'))</title>
    {{-- Pre-compiled Tailwind (Vite) + local jQuery — icons/avatars are offline SVG --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    {{-- Android APK (Capacitor) native feel: no-op in normal browsers --}}
    <script src="{{ asset('js/capacitor-native.js') }}"></script>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
</head>
<body class="bg-gray-50 text-gray-800 pb-20 md:pb-6">

    {{-- ===================== Sticky top header ===================== --}}
    <header class="sticky top-0 z-40 bg-brand-gradient text-white shadow-lg pt-[env(safe-area-inset-top)]">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-bold text-lg">
                    <span class="w-9 h-9 rounded-xl bg-white/15 ring-1 ring-white/25 flex items-center justify-center shadow-sm">
                        <img src="{{ asset('icon.svg') }}" alt="" class="w-6 h-6">
                    </span>
                    {{ config('app.name') }}
                </a>

                {{-- Desktop navigation --}}
                <nav class="hidden md:flex items-center gap-1 text-sm">
                    {{-- Home is the partner's landing tab; the owner starts on the entries workflow --}}
                    @unless(auth()->user()->isOwner())
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('dashboard') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="home" class="w-4 h-4" /> {{ __('messages.home') }}
                        </a>
                    @endunless
                    @if(auth()->user()->isOwner())
                        <a href="{{ route('owner.entries.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.entries.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="clipboard-document-list" class="w-4 h-4" /> {{ __('messages.entries') }}
                        </a>
                        <a href="{{ route('owner.reports.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.reports.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="chart-bar" class="w-4 h-4" /> {{ __('messages.reports') }}
                        </a>
                        <a href="{{ route('owner.expense-heads.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.expense-heads.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="currency-dollar" class="w-4 h-4" /> {{ __('messages.expense_heads_page') }}
                        </a>
                        <a href="{{ route('owner.stock.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.stock.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="building-storefront" class="w-4 h-4" /> {{ __('messages.stock_page') }}
                        </a>
                        <a href="{{ route('owner.productions.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.productions.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="building-office-2" class="w-4 h-4" /> {{ __('messages.productions') }}
                        </a>
                        <a href="{{ route('owner.masters.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.masters.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="cog-6-tooth" class="w-4 h-4" /> {{ __('messages.masters') }}
                        </a>
                        <a href="{{ route('owner.partners.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('owner.partners.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="user-group" class="w-4 h-4" /> {{ __('messages.partners') }}
                        </a>
                    @else
                        <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('entries.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="clipboard-document-list" class="w-4 h-4" /> {{ __('messages.my_entries') }}
                        </a>
                        <a href="{{ route('productions.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('productions.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="building-office-2" class="w-4 h-4" /> {{ __('messages.productions') }}
                        </a>
                        <a href="{{ route('stock-losses.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('stock-losses.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="arrow-trending-down" class="w-4 h-4" /> {{ __('messages.stock_losses') }}
                        </a>
                        <a href="{{ route('stock.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg transition {{ request()->routeIs('stock.*') ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' }}">
                            <x-icon name="building-storefront" class="w-4 h-4" /> {{ __('messages.stock_page') }}
                        </a>
                    @endif
                </nav>

                <div class="flex items-center gap-2">
                    {{-- Name + designation click through to the profile page --}}
                    <a href="{{ route('profile') }}" title="{{ __('messages.profile') }}" class="flex items-center gap-2 rounded-xl px-1.5 py-1 hover:bg-white/10 transition">
                        <span class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium">
                            <x-avatar :user="auth()->user()" size="28" /> {{ auth()->user()->name }}
                        </span>
                        <span class="text-xs px-2.5 py-1 rounded-full ring-1 {{ auth()->user()->isOwner() ? 'bg-amber-400/90 text-amber-900 ring-amber-300/50' : 'bg-white/20 text-white ring-white/25' }}">
                            {{ auth()->user()->isOwner() ? __('messages.role_owner') : __('messages.role_partner') }}
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="{{ __('messages.logout') }}"
                                class="bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white w-10 h-10 rounded-full flex items-center justify-center transition active:scale-95">
                            <x-icon name="arrow-right-on-rectangle" class="w-5 h-5" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ===================== Flash messages ===================== --}}
    <div class="max-w-6xl mx-auto px-4 mt-3 space-y-2">
        @if(session('success'))
            <div class="js-flash alert-success">
                <x-icon name="check-circle" class="w-5 h-5 shrink-0" /> {{ session('success') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="js-flash alert-warning">
                <x-icon name="clock" class="w-5 h-5 shrink-0" /> {{ session('warning') }}
            </div>
        @endif
        @if(session('error'))
            <div class="js-flash alert-error">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0" /> {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="js-flash alert-error">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0" /> {{ $errors->first() }}
            </div>
        @endif
    </div>

    {{-- ===================== Page content ===================== --}}
    <main class="max-w-6xl mx-auto px-4 mt-4">
        @yield('content')
    </main>

    {{-- ===================== Bottom navigation (mobile only) ===================== --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200 shadow-[0_-4px_16px_rgba(0,0,0,0.05)] pb-[env(safe-area-inset-bottom)]">
        <div class="grid {{ auth()->user()->isOwner() ? 'grid-cols-7' : 'grid-cols-5' }} text-center text-[11px]">
            @unless(auth()->user()->isOwner())
                <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('dashboard') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="home" class="w-5 h-5" />
                    {{ __('messages.home') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('dashboard') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
            @endunless
            @if(auth()->user()->isOwner())
                <a href="{{ route('owner.entries.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="clipboard-document-list" class="w-5 h-5" />
                    {{ __('messages.entries') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.entries.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.reports.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.reports.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="chart-bar" class="w-5 h-5" />
                    {{ __('messages.reports') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.reports.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.expense-heads.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.expense-heads.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="currency-dollar" class="w-5 h-5" />
                    {{ __('messages.expense_heads_page') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.expense-heads.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.stock.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.stock.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="building-storefront" class="w-5 h-5" />
                    {{ __('messages.stock_page') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.stock.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.productions.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.productions.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="building-office-2" class="w-5 h-5" />
                    {{ __('messages.productions') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.productions.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.masters.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.masters.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="cog-6-tooth" class="w-5 h-5" />
                    {{ __('messages.masters') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.masters.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('owner.partners.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('owner.partners.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="user-group" class="w-5 h-5" />
                    {{ __('messages.partners') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('owner.partners.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
            @else
                <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="clipboard-document-list" class="w-5 h-5" />
                    {{ __('messages.my_entries') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('entries.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('productions.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('productions.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="building-office-2" class="w-5 h-5" />
                    {{ __('messages.productions') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('productions.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('stock-losses.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('stock-losses.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="arrow-trending-down" class="w-5 h-5" />
                    {{ __('messages.stock_losses') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('stock-losses.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
                <a href="{{ route('stock.index') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('stock.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <x-icon name="building-storefront" class="w-5 h-5" />
                    {{ __('messages.stock_page') }}
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('stock.*') ? 'bg-emerald-500' : 'bg-transparent' }}"></span>
                </a>
            @endif
        </div>
    </nav>

    {{-- ===================== Shared jQuery behaviors ===================== --}}
    <script>
        $(function () {
            // Auto-dismiss flash messages after 2.5s.
            window.setTimeout(function () {
                $('.js-flash').fadeOut(300);
            }, 2500);

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
