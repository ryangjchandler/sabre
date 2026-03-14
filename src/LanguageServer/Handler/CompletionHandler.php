<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Handler;

use Amp\Promise;
use Amp\Success;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use RyanChandler\Sabre\LanguageServer\Feature\Completion\BladeCompletionProvider;

final class CompletionHandler implements CanRegisterCapabilities, Handler
{
    public function __construct(private readonly BladeCompletionProvider $provider) {}

    public function methods(): array
    {
        return [
            'textDocument/completion' => 'completion',
        ];
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->completionProvider = new CompletionOptions(['@', '<', ' ', ':', '-', '.']);
    }

    /**
     * @return Promise<CompletionList>
     */
    public function completion(CompletionParams $params): Promise
    {
        return new Success($this->provider->provide($params));
    }
}
