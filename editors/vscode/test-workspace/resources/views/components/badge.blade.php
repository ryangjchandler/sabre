@props([
    'variant' => 'default',
    'rounded' => false,
])

<span {{ $attributes->class(['badge', 'badge-'.$variant, 'badge-rounded' => $rounded]) }}>
    {{ $slot ?? '' }}
</span>
