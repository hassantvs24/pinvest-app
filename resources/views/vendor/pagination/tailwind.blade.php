@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        {{-- Mobile: only Previous / Next buttons --}}
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="flex-1 text-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed">
                    ← {{ __('messages.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex-1 text-center px-4 py-2 text-sm font-medium text-white bg-emerald-600 border border-emerald-600 rounded-lg hover:bg-emerald-700">
                    ← {{ __('messages.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex-1 text-center px-4 py-2 text-sm font-medium text-white bg-emerald-600 border border-emerald-600 rounded-lg hover:bg-emerald-700">
                    {{ __('messages.next') }} →
                </a>
            @else
                <span class="flex-1 text-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed">
                    {{ __('messages.next') }} →
                </span>
            @endif
        </div>

        {{-- Desktop: full pagination with page numbers --}}
        <div class="hidden sm:flex sm:items-center sm:justify-center">
            <span class="inline-flex rounded-lg overflow-hidden border border-gray-200 shadow-sm">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-white cursor-not-allowed">
                        ←
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-white hover:bg-emerald-50 hover:text-emerald-700">
                        ←
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span aria-disabled="true" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white cursor-default">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex items-center px-3 py-2 text-sm font-bold text-white bg-emerald-600 cursor-default">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-white hover:bg-emerald-50 hover:text-emerald-700">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-white hover:bg-emerald-50 hover:text-emerald-700">
                        →
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-white cursor-not-allowed">
                        →
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
