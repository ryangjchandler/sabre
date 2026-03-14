<?php

namespace App\View\Components;

use Illuminate\View\Component;

class AlertBanner extends Component
{
    public function __construct(
        public string $title,
        public string $variant = 'info',
        public bool $dismissible = false,
        public ?string $iconName = null,
    ) {
    }
}
