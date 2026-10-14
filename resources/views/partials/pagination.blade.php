@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="pager">
    <div>{!! __('Showing :from–:to of :total', ['from' => '<b>'.($paginator->firstItem() ?? 0), 'to' => ($paginator->lastItem() ?? 0).'</b>', 'total' => '<b>'.number_format($paginator->total()).'</b>']) !!}</div>
    @if ($paginator->hasPages())
    <div class="pages">
        @if ($paginator->onFirstPage())
            <span class="disabled">‹ {{ __('Prev') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ {{ __('Prev') }}</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="disabled">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="current">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }} ›</a>
        @else
            <span class="disabled">{{ __('Next') }} ›</span>
        @endif
    </div>
    @endif
</nav>
@endif
