@props([
    'title' => null,
])

<section {{ $attributes }}>
    @if($title)
        <h2>{{ $title }}</h2>
    @endif

    {{ $slot }}
</section>
