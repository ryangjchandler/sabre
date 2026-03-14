<div>
    {{-- Component name completion after <x- --}}
    <x-

    {{-- Anonymous component attribute completion --}}
    <x-alert di

    {{-- Required default slot: should complete with closing tag --}}
    <x-panel

    {{-- Optional slot: should complete as self-closing --}}
    <x-badge

    {{-- Class component name + attribute completion --}}
    <x-alert-banner ic

    {{-- Another class component attribute completion --}}
    <x-toast po

    {{-- Nested anonymous component completion --}}
    <x-form.input la

    {{-- Named slot completions inside modal --}}
    <x-modal>
        <x-slot:title>
        </x-slot:title>

        <x-slot
    </x-modal>
</div>
