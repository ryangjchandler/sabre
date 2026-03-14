@props([
    'subtitle' => null,
])

<header {{ $attributes }}>
    {{ $slot }}

    @if($subtitle)
        <p>{{ $subtitle }}</p>
    @endif
</header>
