@php($last = max(1, $paginator->lastPage()))
<div class="pager">
    <button type="button" class="navbutton {{ $paginator->onFirstPage() ? 'disabled' : '' }}" wire:click="gotoPage(1, '{{ $paginator->getPageName() }}')" title="{{ __('First page') }}" aria-label="{{ __('First page') }}">&laquo;</button>
    <button type="button" class="navbutton {{ $paginator->onFirstPage() ? 'disabled' : '' }}" wire:click="previousPage('{{ $paginator->getPageName() }}')" title="{{ __('Previous page') }}" aria-label="{{ __('Previous page') }}">&lsaquo;</button>
    <span class="current">{{ $paginator->currentPage() }} / {{ $last }}</span>
    <button type="button" class="navbutton {{ $paginator->hasMorePages() ? '' : 'disabled' }}" wire:click="nextPage('{{ $paginator->getPageName() }}')" title="{{ __('Next page') }}" aria-label="{{ __('Next page') }}">&rsaquo;</button>
    <button type="button" class="navbutton {{ $paginator->hasMorePages() ? '' : 'disabled' }}" wire:click="gotoPage({{ $last }}, '{{ $paginator->getPageName() }}')" title="{{ __('Last page') }}" aria-label="{{ __('Last page') }}">&raquo;</button>
</div>
