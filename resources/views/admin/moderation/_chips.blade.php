@php
    $kind = $kind ?? 'type';
    $val = $val ?? '';
    $label = $label ?? $val;
@endphp
<span class="mod-chip {{ $kind === 'type' ? 't-' : ($kind === 'state' ? 's-' : 'r-') }}{{ preg_replace('/[^a-z]/', '', $val) }}">{{ $label }}</span>
