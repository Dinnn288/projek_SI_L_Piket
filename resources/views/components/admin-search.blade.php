@props(['placeholder' => 'Cari data...', 'value' => null])
@php
    $value = $value ?? request('search', '');
@endphp
<form method="GET" action="{{ url()->current() }}" class="sl-search" role="search" data-sl-search>
    <span class="sl-search-icon" aria-hidden="true">&#128269;</span>
    <input type="text" name="search" value="{{ $value }}" placeholder="{{ $placeholder }}" aria-label="Cari data" maxlength="100" data-sl-search-input autocomplete="off">
    {{ $slot ?? '' }}
    <button type="submit" class="btn" data-sl-search-submit>Cari</button>
    @if (request()->filled('search') || collect(request()->except(['search', 'page']))->filter()->isNotEmpty())
        <a href="{{ url()->current() }}" class="btn btn-secondary">Reset</a>
    @endif
</form>
