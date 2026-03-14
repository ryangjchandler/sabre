@props([
    'name',
    'label' => null,
    'error' => null,
])

<label>
    @if($label)
        <span>{{ $label }}</span>
    @endif

    <input name="{{ $name }}" {{ $attributes }} />

    @if($error)
        <small>{{ $error }}</small>
    @endif
</label>
