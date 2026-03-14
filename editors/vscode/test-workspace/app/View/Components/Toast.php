<?php

namespace App\View\Components;

use Illuminate\View\Component;

final class Toast extends Component
{
    public function __construct(
        public string $message,
        public string $variant = 'info',
        public bool $dismissible = true,
        public ?string $position = null,
    ) {}
}
