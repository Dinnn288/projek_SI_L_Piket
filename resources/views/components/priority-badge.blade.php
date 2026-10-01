@props(['value' => ''])
@php
    $map = ['rendah' => ['badge-priority-rendah', 'Rendah'], 'sedang' => ['badge-priority-sedang', 'Sedang'], 'tinggi' => ['badge-priority-tinggi', 'Tinggi']];
    [$cls, $label] = $map[$value] ?? ['badge-priority-sedang', $value];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>

