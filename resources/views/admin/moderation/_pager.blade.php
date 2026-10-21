@php $pages = max(1, (int) ceil($total / $per)); @endphp
@if ($pages > 1)
    <nav class="d-flex justify-content-between align-items-center">
        <a class="btn btn-sm btn-outline-secondary {{ $pg <= 1 ? 'disabled' : '' }}" href="{{ request()->fullUrlWithQuery(['page' => max(1, $pg - 1)]) }}">&larr; {{ __('moderation.pager_prev') }}</a>
        <span class="small">{{ $pg }} / {{ $pages }}</span>
        <a class="btn btn-sm btn-outline-secondary {{ $pg >= $pages ? 'disabled' : '' }}" href="{{ request()->fullUrlWithQuery(['page' => min($pages, $pg + 1)]) }}">{{ __('moderation.pager_next') }} &rarr;</a>
    </nav>
@endif
