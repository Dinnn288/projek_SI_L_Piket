@props(['placeholder' => 'Cari data...', 'value' => null])
@php
    $value = $value ?? request('search', '');
@endphp
<form method="GET" action="{{ url()->current() }}" class="sl-search" role="search">
    <span class="sl-search-icon" aria-hidden="true">&#128269;</span>
    <input type="text" name="search" value="{{ $value }}" placeholder="{{ $placeholder }}" aria-label="Cari data" maxlength="100">
    @foreach (request()->except(['search', 'page']) as $key => $val)
        @if (! is_array($val))
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
        @endif
    @endforeach
    <button type="submit" class="btn">Cari</button>
    @if (request()->filled('search'))
        <a href="{{ url()->current() }}" class="btn btn-secondary">Reset</a>
    @endif
</form>
