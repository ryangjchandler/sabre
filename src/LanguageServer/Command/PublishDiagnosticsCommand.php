<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Command;

use Amp\Promise;
use Amp\Success;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServer\Core\Server\ClientApi;

final class PublishDiagnosticsCommand implements Command
{
    public function __construct(private readonly ClientApi $clientApi)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $diagnostics
     * @return Promise<null>
     */
    public function __invoke(string $uri, array $diagnostics = [], ?int $version = null): Promise
    {
        $this->clientApi->diagnostics()->publishDiagnostics($uri, $version, $diagnostics);

        return new Success(null);
    }
}
