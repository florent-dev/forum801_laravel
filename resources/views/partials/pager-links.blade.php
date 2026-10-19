@php($last = max(1, $paginator->lastPage()))
<div class="pager">
    <a class="navbutton {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->url(1) }}" title="{{ __('First page') }}" aria-label="{{ __('First page') }}">&laquo;</a>
    <a class="navbutton {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() }}" title="{{ __('Previous page') }}" aria-label="{{ __('Previous page') }}">&lsaquo;</a>
    <span class="current">{{ $paginator->currentPage() }} / {{ $last }}</span>
    <a class="navbutton {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() }}" title="{{ __('Next page') }}" aria-label="{{ __('Next page') }}">&rsaquo;</a>
    <a class="navbutton {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->url($last) }}" title="{{ __('Last page') }}" aria-label="{{ __('Last page') }}">&raquo;</a>
</div>
