@props(['paginator' => null])
@if ($paginator && $paginator->hasPages())
    <nav class="sl-pagination" aria-label="Navigasi halaman">
        <span class="sl-pagination-info">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
        </span>
        <span class="sl-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="sl-page disabled" aria-disabled="true">‹</span>
            @else
                <a class="sl-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
            @endif
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="sl-page active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="sl-page" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a class="sl-page" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
            @else
                <span class="sl-page disabled" aria-disabled="true">›</span>
            @endif
        </span>
    </nav>
@endif
