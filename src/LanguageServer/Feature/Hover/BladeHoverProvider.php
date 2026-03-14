<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Feature\Hover;

use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverParams;
use RyanChandler\Sabre\Blade\ForteDocumentParser;
use RyanChandler\Sabre\Blade\Hover\ForteHoverProvider;
use RuntimeException;

final class BladeHoverProvider
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ForteDocumentParser $documentParser,
        private readonly ForteHoverProvider $hoverProvider,
    ) {
    }

    public function provide(HoverParams $params): ?Hover
    {
        $uri = $params->textDocument->uri;

        try {
            if ($this->workspace->has($uri)) {
                $item = $this->workspace->get($uri);
                $filePath = $this->documentParser->uriToPath($uri);
                $document = $this->documentParser->parse($item->text, $filePath);
            } else {
                $document = $this->documentParser->parseUri($uri);
            }
        } catch (RuntimeException) {
            return null;
        }

        return $this->hoverProvider->provide(
            $document,
            $params->position->line,
            $params->position->character
        );
    }
}
