@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">

        {{-- Mobile: only Previous / Next buttons --}}
        <div class="flex items-center justify-between gap-2 w-full sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="flex-1 text-center px-4 py-2.5 text-sm font-semibold text-gray-400 bg-white ring-1 ring-gray-200 rounded-xl cursor-not-allowed">
                    <x-icon name="arrow-left" class="w-4 h-4" /> {{ __('messages.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-500 rounded-xl shadow-sm shadow-emerald-600/25 active:scale-[0.97] transition">
                    <x-icon name="arrow-left" class="w-4 h-4" /> {{ __('messages.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-500 rounded-xl shadow-sm shadow-emerald-600/25 active:scale-[0.97] transition">
                    {{ __('messages.next') }} <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            @else
                <span class="flex-1 text-center px-4 py-2.5 text-sm font-semibold text-gray-400 bg-white ring-1 ring-gray-200 rounded-xl cursor-not-allowed">
                    {{ __('messages.next') }} <x-icon name="arrow-right" class="w-4 h-4" />
                </span>
            @endif
        </div>

        {{-- Desktop: full pagination with page numbers --}}
        <div class="hidden sm:flex sm:items-center sm:justify-center sm:flex-1">
            <span class="inline-flex rounded-xl overflow-hidden ring-1 ring-gray-200 shadow-sm bg-white">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 cursor-not-allowed">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span aria-disabled="true" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 cursor-default">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex items-center px-3.5 py-2 text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 cursor-default">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition">
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 cursor-not-allowed">
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
