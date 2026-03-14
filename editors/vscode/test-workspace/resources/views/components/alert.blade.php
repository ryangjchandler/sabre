@props([
    'type' => 'info',
    'dismissible' => false,
    'iconName' => null,
])

<div class="alert alert-{{ $type }}" {{ $attributes }}>
    @if($iconName)
        <span class="icon">{{ $iconName }}</span>
    @endif

    {{ $slot }}

    @if($dismissible)
        <button type="button" aria-label="Close">x</button>
    @endif
</div>
