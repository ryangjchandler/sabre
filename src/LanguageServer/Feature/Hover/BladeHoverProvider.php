<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Feature\Hover;

use Forte\Ast\Elements\ElementNameNode;
use Forte\Ast\Elements\ElementNode;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\MarkupContent;
use Phpactor\LanguageServerProtocol\MarkupKind;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use RuntimeException;
use RyanChandler\Sabre\Blade\Components\BladeComponentCatalog;
use RyanChandler\Sabre\Blade\ForteDocumentParser;
use RyanChandler\Sabre\Blade\Hover\ForteHoverProvider;

final class BladeHoverProvider
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ForteDocumentParser $documentParser,
        private readonly BladeComponentCatalog $componentCatalog,
        private readonly ForteHoverProvider $hoverProvider,
    ) {}

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

        $node = $document->findNodeAtPosition($params->position->line + 1, $params->position->character + 1);

        if ($node === null && $params->position->character > 0) {
            $node = $document->findNodeAtPosition($params->position->line + 1, $params->position->character);
        }

        $componentElement = $this->resolveComponentElementFromNode(is_object($node) ? $node : null);

        if ($componentElement instanceof ElementNode && $componentElement->isComponent()) {
            $hover = $this->componentHover($uri, $document, $componentElement);

            if ($hover instanceof Hover) {
                return $hover;
            }
        }

        return $this->hoverProvider->provide($document, $params->position->line, $params->position->character);
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

    private function componentHover(string $uri, object $document, ElementNode $element): ?Hover
    {
        $tagName = strtolower($element->tagNameText());

        if (! str_starts_with($tagName, 'x-')) {
            return null;
        }

        $componentName = substr($tagName, 2);

        if ($componentName === '') {
            return null;
        }

        $metadata = $this->componentCatalog->componentHoverMetadata($uri, $componentName);

        if ($metadata === null) {
            return null;
        }

        $header = sprintf('Blade component `%s`', $metadata['tag']);

        if ($metadata['className'] !== null) {
            $header .= sprintf(' (`%s`)', $metadata['className']);
        }

        $propsSection = $this->renderPropsSection($metadata['props']);
        $slotsSection = $this->renderSlotsSection($metadata['slots']);

        $title = $metadata['className'] !== null
            ? sprintf('### `%s` (`%s`)', $metadata['tag'], $metadata['className'])
            : sprintf('### `%s`', $metadata['tag']);

        $pathHref = $this->filePathToUri($metadata['absolutePath']);
        $clickablePath = sprintf('[`%s`](%s)', $metadata['relativePath'], $pathHref);

        $content = implode("\n", [
            $title,
            '',
            sprintf('**Path**: %s', $clickablePath),
            '',
            '#### Props',
            ...$propsSection,
            '',
            '#### Slots',
            ...$slotsSection,
            '',
            sprintf('*Resolved from `%s`*', $metadata['className'] !== null ? 'class + template metadata' : 'template metadata'),
        ]);

        $nameNode = $element->tagName();
        $start = $document->getLineAndColumnForOffset($nameNode->startOffset());
        $end = $document->getLineAndColumnForOffset($nameNode->endOffset());

        return new Hover(
            new MarkupContent(MarkupKind::MARKDOWN, $content),
            new Range(
                new Position($start['line'] - 1, $start['column'] - 1),
                new Position($end['line'] - 1, $end['column'] - 1)
            )
        );
    }

    private function filePathToUri(string $path): string
    {
        return 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    /**
     * @param  list<array{name: string, isBoolean: bool, required: bool, type: string|null, default: string|null, source: string}>  $props
     * @return list<string>
     */
    private function renderPropsSection(array $props): array
    {
        if ($props === []) {
            return ['- _(none)_'];
        }

        $required = [];
        $optional = [];

        foreach ($props as $prop) {
            $details = [];

            if ($prop['type'] !== null) {
                $details[] = sprintf('type `%s`', $prop['type']);
            }

            if ($prop['default'] !== null) {
                $details[] = sprintf('default `%s`', $prop['default']);
            }

            if ($prop['isBoolean']) {
                $details[] = 'boolean';
            }

            $details[] = sprintf('source `%s`', $prop['source']);

            $line = sprintf('- `%s` (%s)', $prop['name'], implode(', ', $details));

            if ($prop['required']) {
                $required[] = $line;
            } else {
                $optional[] = $line;
            }
        }

        return [
            '**Required**',
            ...($required === [] ? ['- _(none)_'] : $required),
            '',
            '**Optional**',
            ...($optional === [] ? ['- _(none)_'] : $optional),
        ];
    }

    /**
     * @param  list<array{name: string, required: bool}>  $slots
     * @return list<string>
     */
    private function renderSlotsSection(array $slots): array
    {
        if ($slots === []) {
            return ['- _(none)_'];
        }

        $required = [];
        $optional = [];

        foreach ($slots as $slot) {
            $line = sprintf('- `%s`', $slot['name']);

            if ($slot['required']) {
                $required[] = $line;
            } else {
                $optional[] = $line;
            }
        }

        return [
            '**Required**',
            ...($required === [] ? ['- _(none)_'] : $required),
            '',
            '**Optional**',
            ...($optional === [] ? ['- _(none)_'] : $optional),
        ];
    }
}
