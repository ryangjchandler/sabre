<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Handler;

use Amp\Promise;
use Amp\Success;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use RyanChandler\Sabre\LanguageServer\Feature\Hover\BladeHoverProvider;

final class HoverHandler implements Handler, CanRegisterCapabilities
{
    public function __construct(private readonly BladeHoverProvider $provider)
    {
    }

    public function methods(): array
    {
        return [
            'textDocument/hover' => 'hover',
        ];
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->hoverProvider = true;
    }

    /**
     * @return Promise<Hover|null>
     */
    public function hover(HoverParams $params): Promise
    {
        return new Success($this->provider->provide($params));
    }
}
