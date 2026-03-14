<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Feature\Definition;

use Forte\Ast\Elements\ElementNameNode;
use Forte\Ast\Elements\ElementNode;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use RuntimeException;
use RyanChandler\Sabre\Blade\Components\BladeComponentCatalog;
use RyanChandler\Sabre\Blade\ForteDocumentParser;

final class BladeDefinitionProvider
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ForteDocumentParser $documentParser,
        private readonly BladeComponentCatalog $componentCatalog,
    ) {}

    public function provide(DefinitionParams $params): ?Location
    {
        $uri = $params->textDocument->uri;
        $documentText = $this->documentText($uri);

        if ($documentText === null) {
            return null;
        }

        $document = $this->parseDocumentFromText($uri, $documentText);
        $node = $this->nodeAtPosition($document, $params->position->line, $params->position->character);
        $element = $this->resolveComponentElementFromNode($node);

        if (! $element instanceof ElementNode || ! $element->isComponent()) {
            return null;
        }

        $tagName = strtolower($element->tagNameText());
        if (! str_starts_with($tagName, 'x-')) {
            return null;
        }

        $componentName = substr($tagName, 2);
        if ($componentName === '') {
            return null;
        }

        $targetPath = $this->componentCatalog->resolveDefinitionPath($uri, $componentName);

        if ($targetPath === null) {
            return null;
        }

        return new Location(
            $this->pathToUri($targetPath),
            new Range(new Position(0, 0), new Position(0, 0))
        );
    }

    private function parseDocumentFromText(string $uri, string $documentText): object
    {
        try {
            $filePath = $this->documentParser->uriToPath($uri);

            return $this->documentParser->parse($documentText, $filePath);
        } catch (RuntimeException) {
            return $this->documentParser->parse($documentText);
        }
    }

    private function nodeAtPosition(object $document, int $line, int $character): ?object
    {
        if (! method_exists($document, 'findNodeAtPosition')) {
            return null;
        }

        $node = $document->findNodeAtPosition($line + 1, $character + 1);

        if ($node === null && $character > 0) {
            $node = $document->findNodeAtPosition($line + 1, $character);
        }

        return is_object($node) ? $node : null;
    }

    private function resolveComponentElementFromNode(?object $node): ?ElementNode
    {
        if ($node instanceof ElementNode) {
            return $node;
        }

        if ($node instanceof ElementNameNode) {
            $parent = $node->getParent();

            if ($parent instanceof ElementNode) {
                return $parent;
            }
        }

        return null;
    }

    private function pathToUri(string $path): string
    {
        return 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    private function documentText(string $uri): ?string
    {
        if ($this->workspace->has($uri)) {
            return $this->workspace->get($uri)->text;
        }

        try {
            $path = $this->documentParser->uriToPath($uri);
        } catch (RuntimeException) {
            return null;
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        return $content;
    }
}
