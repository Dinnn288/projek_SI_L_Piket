@props(['value' => ''])
@php
    $map = ['menunggu' => ['badge-menunggu', 'Menunggu'], 'dalam_penanganan' => ['badge-proses', 'Dalam Penanganan'], 'diteruskan' => ['badge-forward', 'Diteruskan'], 'selesai' => ['badge-selesai', 'Selesai']];
    [$cls, $label] = $map[$value] ?? ['badge-proses', $value];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>

