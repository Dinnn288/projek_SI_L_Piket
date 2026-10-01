@props(['value' => '', 'map' => []])
@php
    [$cls, $label] = $map[$value] ?? ['badge-proses', $value];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>

