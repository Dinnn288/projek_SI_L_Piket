@props(['name' => 'filter', 'label' => 'Filter', 'options' => [], 'placeholder' => null])
@php
    $current = request($name, '');
@endphp
<label class="sr-only" for="filter-{{ $name }}" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">{{ $label }}</label>
<select id="filter-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}" data-sl-filter>
    <option value="">{{ $placeholder ?? $label . ': Semua' }}</option>
    @foreach ($options as $value => $text)
        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
    @endforeach
</select>

