@props([
    'size' => 'md',
])

<div class="modal modal-{{ $size }}" {{ $attributes }}>
    <header class="modal-header">
        {{ $title }}
    </header>

    <section class="modal-body">
        {{ $slot }}
    </section>

    <footer class="modal-footer">
        {{ $footer }}
    </footer>
</div>
