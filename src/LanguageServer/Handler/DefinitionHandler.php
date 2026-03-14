<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Handler;

use Amp\Promise;
use Amp\Success;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use RyanChandler\Sabre\LanguageServer\Feature\Definition\BladeDefinitionProvider;

final class DefinitionHandler implements CanRegisterCapabilities, Handler
{
    public function __construct(private readonly BladeDefinitionProvider $provider) {}

    public function methods(): array
    {
        return [
            'textDocument/definition' => 'definition',
        ];
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->definitionProvider = true;
    }

    /**
     * @return Promise<Location|null>
     */
    public function definition(DefinitionParams $params): Promise
    {
        return new Success($this->provider->provide($params));
    }
}
