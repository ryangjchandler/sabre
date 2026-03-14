<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Feature\Completion;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Parser\Directives\Directives;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Psr\Log\LoggerInterface;
use RyanChandler\Sabre\Blade\Components\BladeComponentCatalog;
use RyanChandler\Sabre\Blade\ForteDocumentParser;
use RuntimeException;

final class BladeCompletionProvider
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ForteDocumentParser $documentParser,
        private readonly BladeComponentCatalog $componentCatalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function provide(CompletionParams $params): CompletionList
    {
        $uri = $params->textDocument->uri;
        $documentText = $this->documentText($uri);

        if ($documentText === null) {
            return new CompletionList(false, []);
        }

        $linePrefix = $this->linePrefixFromText($documentText, $params->position->line, $params->position->character);
        $documentPrefix = $this->documentPrefixFromText($documentText, $params->position->line, $params->position->character);
        $document = $this->parseDocumentFromText($uri, $documentText);
        $node = $this->nodeAtPosition($document, $params->position->line, $params->position->character);
        $nodeComponent = $this->componentNameFromNodeContext($node, false);
        $slotParentComponent = $this->componentNameFromNodeContext($node, true);

        if ($documentPrefix !== null && $linePrefix !== null) {
            $slotContext = $this->slotCompletionContext($documentPrefix, $linePrefix, $slotParentComponent);

            if ($slotContext !== null) {
                $this->logger->debug('Slot completion context detected.', $slotContext);

                return new CompletionList(false, $this->slotCompletions(
                    $uri,
                    $slotContext['component'],
                    $slotContext['style'],
                    $slotContext['typedPrefix']
                ));
            }

            if (str_contains($linePrefix, '<x-slot')) {
                $this->logger->debug('No slot completion context matched.', [
                    'linePrefix' => $linePrefix,
                    'nodeComponent' => $nodeComponent,
                    'slotParentComponent' => $slotParentComponent,
                ]);
            }
        }

        if ($documentPrefix !== null && $this->isComponentAttributeContext($node, $documentPrefix)) {
            $componentContext = $this->componentAttributeContext($documentPrefix, $nodeComponent);

            if ($componentContext !== null) {
                return new CompletionList(false, $this->componentAttributeCompletions(
                    $uri,
                    $componentContext['component'],
                    $componentContext['attributePrefix']
                ));
            }
        }

        if ($this->isComponentNameContext($node, $linePrefix)) {
            return new CompletionList(false, $this->componentCompletions($uri));
        }

        if (!$this->shouldProvideDirectiveCompletions($node, $linePrefix)) {
            return new CompletionList(false, []);
        }

        $items = [];
        $directives = array_keys(Directives::withDefaults()->allDirectives());
        sort($directives);

        foreach ($directives as $directive) {
            $label = sprintf('@%s', $directive);

            $items[] = new CompletionItem(
                $label,
                null,
                CompletionItemKind::KEYWORD,
                null,
                'Laravel Blade directive',
                null,
                null,
                null,
                $directive,
                $directive,
                $directive
            );
        }

        return new CompletionList(false, $items);
    }

    private function shouldProvideDirectiveCompletions(object|null $node, ?string $linePrefix): bool
    {
        if ($linePrefix === null) {
            return false;
        }

        if (preg_match('/@@[A-Za-z0-9_\-]*$/', $linePrefix) === 1) {
            return false;
        }

        if (preg_match('/(^|[^@])@[A-Za-z0-9_\-]*$/', $linePrefix) !== 1) {
            return false;
        }

        if ($node instanceof DirectiveNode || $node instanceof DirectiveBlockNode) {
            return true;
        }

        if ($node instanceof ElementNode) {
            return false;
        }

        return true;
    }

    private function isComponentNameContext(object|null $node, ?string $linePrefix): bool
    {
        if ($linePrefix === null) {
            return false;
        }

        $matchesOpenComponentPrefix = preg_match('/<x-[A-Za-z0-9_\-.:]*$/', $linePrefix) === 1;

        if ($node instanceof ElementNode && $node->isComponent()) {
            return $matchesOpenComponentPrefix;
        }

        return $matchesOpenComponentPrefix;
    }

    private function isComponentAttributeContext(object|null $node, string $documentPrefix): bool
    {
        if (preg_match('/<x-[A-Za-z0-9_\-.:]+\s[^>]*$/s', $documentPrefix) !== 1) {
            return false;
        }

        if ($node instanceof ElementNode) {
            return $node->isComponent();
        }

        return true;
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

    private function nodeAtPosition(object $document, int $line, int $character): object|null
    {
        if (!method_exists($document, 'findNodeAtPosition')) {
            return null;
        }

        $node = $document->findNodeAtPosition($line + 1, $character + 1);

        if ($node === null && $character > 0) {
            $node = $document->findNodeAtPosition($line + 1, $character);
        }

        return is_object($node) ? $node : null;
    }

    /**
     * @return list<CompletionItem>
     */
    private function componentCompletions(string $uri): array
    {
        $items = [];

        foreach ($this->componentCatalog->discoverForDocumentUri($uri) as $componentName) {
            $label = sprintf('x-%s', $componentName);
            $hasRequiredSlots = $this->componentCatalog->hasRequiredSlotsForComponent($uri, $componentName);

            $insertText = $hasRequiredSlots
                ? sprintf('%s>$0</x-%s>', $componentName, $componentName)
                : sprintf('%s />', $componentName);

            $insertTextFormat = $hasRequiredSlots ? InsertTextFormat::SNIPPET : null;

            $items[] = new CompletionItem(
                $label,
                null,
                CompletionItemKind::CLASS_,
                null,
                'Blade anonymous component',
                null,
                null,
                null,
                $label,
                $label,
                $insertText,
                $insertTextFormat
            );
        }

        return $items;
    }

    /**
     * @return list<CompletionItem>
     */
    private function componentAttributeCompletions(string $uri, string $componentName, string $typedPrefix): array
    {
        $defaultAttributes = [
            ['name' => 'class', 'isBoolean' => false],
            ['name' => 'id', 'isBoolean' => false],
            ['name' => 'style', 'isBoolean' => false],
            ['name' => 'wire:model', 'isBoolean' => false],
            ['name' => 'wire:click', 'isBoolean' => false],
            ['name' => 'x-data', 'isBoolean' => false],
            ['name' => 'x-show', 'isBoolean' => false],
            ['name' => 'x-on:click', 'isBoolean' => false],
        ];

        $definitions = array_merge(
            $this->componentCatalog->attributeDefinitionsForComponent($uri, $componentName),
            $defaultAttributes
        );

        $mergedDefinitions = [];

        foreach ($definitions as $definition) {
            $name = $definition['name'];

            if (!isset($mergedDefinitions[$name])) {
                $mergedDefinitions[$name] = $definition;
                continue;
            }

            $mergedDefinitions[$name]['isBoolean'] = $mergedDefinitions[$name]['isBoolean'] || $definition['isBoolean'];
        }

        ksort($mergedDefinitions);

        $items = [];

        foreach ($mergedDefinitions as $attribute => $definition) {
            $isBoolean = $definition['isBoolean'];
            if ($typedPrefix === '' || str_starts_with($attribute, $typedPrefix)) {
                $insertText = $isBoolean ? $attribute : sprintf('%s="$1"', $attribute);
                $insertTextFormat = $isBoolean ? null : InsertTextFormat::SNIPPET;

                $items[] = new CompletionItem(
                    $attribute,
                    null,
                    CompletionItemKind::PROPERTY,
                    null,
                    'Blade component attribute',
                    null,
                    null,
                    null,
                    $attribute,
                    $attribute,
                    $insertText,
                    $insertTextFormat
                );
            }

            $boundAttribute = sprintf(':%s', $attribute);
            if ($typedPrefix !== '' && !str_starts_with($boundAttribute, $typedPrefix)) {
                continue;
            }

            $boundInsertText = sprintf('%s="$1"', $boundAttribute);
            $boundInsertTextFormat = InsertTextFormat::SNIPPET;

            $items[] = new CompletionItem(
                $boundAttribute,
                null,
                CompletionItemKind::PROPERTY,
                null,
                'Blade bound component attribute',
                null,
                null,
                null,
                $boundAttribute,
                $boundAttribute,
                $boundInsertText,
                $boundInsertTextFormat
            );
        }

        return $items;
    }

    /**
     * @return array{component: string, attributePrefix: string}|null
     */
    private function componentAttributeContext(string $documentPrefix, ?string $nodeComponentName = null): ?array
    {
        if (preg_match('/<x-([A-Za-z0-9_\-.:]+)([^>]*)$/s', $documentPrefix, $matches) !== 1) {
            if ($nodeComponentName === null) {
                return null;
            }

            return [
                'component' => $nodeComponentName,
                'attributePrefix' => '',
            ];
        }

        $componentName = $matches[1];
        $attributesText = $matches[2] ?? '';
        $attributesText = rtrim($attributesText);
        $attributesText = preg_replace('/\/\s*$/', '', $attributesText) ?? $attributesText;

        if ($attributesText === '') {
            return [
                'component' => $componentName,
                'attributePrefix' => '',
            ];
        }

        if (preg_match('/(?:^|\s)([A-Za-z_:][-A-Za-z0-9_:.]*)?$/', $attributesText, $attributeMatch) !== 1) {
            return null;
        }

        $typedPrefix = $attributeMatch[1] ?? '';

        return [
            'component' => $componentName,
            'attributePrefix' => $typedPrefix,
        ];
    }

    /**
     * @return array{component: string, style: string, typedPrefix: string}|null
     */
    private function slotCompletionContext(string $documentPrefix, string $linePrefix, ?string $nodeSlotParent = null): ?array
    {
        $activeComponent = $nodeSlotParent ?? $this->activeComponentNameFromPrefix($documentPrefix);

        if ($activeComponent === null) {
            if (str_contains($linePrefix, '<x-slot')) {
                $this->logger->debug('Unable to resolve active component for slot completion.', [
                    'linePrefix' => $linePrefix,
                ]);
            }

            return null;
        }

        if (preg_match('/<x-slot:([^\s>]*)$/u', $linePrefix, $matches) === 1) {
            return [
                'component' => $activeComponent,
                'style' => 'shorthand',
                'typedPrefix' => $this->normalizeSlotPrefix($matches[1] ?? ''),
            ];
        }

        if (preg_match('/<x-slot\s+[^>]*\bname\s*=\s*["\'"“”‘’]([^"\'“”‘’]*)$/u', $linePrefix, $matches) === 1) {
            return [
                'component' => $activeComponent,
                'style' => 'legacy',
                'typedPrefix' => $this->normalizeSlotPrefix($matches[1] ?? ''),
            ];
        }

        if (preg_match('/<x-slot\s+[^>]*\bname\s*=\s*([A-Za-z0-9_\-.:]*)$/u', $linePrefix, $matches) === 1) {
            return [
                'component' => $activeComponent,
                'style' => 'legacy',
                'typedPrefix' => $this->normalizeSlotPrefix($matches[1] ?? ''),
            ];
        }

        if (preg_match('/<x-slot\s*$/', $linePrefix) === 1 || preg_match('/<x-slot\s+[^>]*$/', $linePrefix) === 1) {
            return [
                'component' => $activeComponent,
                'style' => 'both',
                'typedPrefix' => '',
            ];
        }

        return null;
    }

    /**
     * @return list<CompletionItem>
     */
    private function slotCompletions(string $uri, string $componentName, string $style, string $typedPrefix): array
    {
        $slots = $this->componentCatalog->slotDefinitionsForComponent($uri, $componentName);

        if ($slots === []) {
            $this->logger->debug('No named slot definitions discovered for component.', [
                'component' => $componentName,
                'style' => $style,
                'typedPrefix' => $typedPrefix,
            ]);

            return [];
        }

        $items = [];

        foreach ($slots as $slot) {
            $slotName = $slot['name'];

            if ($typedPrefix !== '' && !str_starts_with($slotName, $typedPrefix)) {
                continue;
            }

            if ($style === 'shorthand' || $style === 'both') {
                $label = sprintf('x-slot:%s', $slotName);
                $insertText = $style === 'both'
                    ? sprintf('x-slot:%s>$0</x-slot:%s>', $slotName, $slotName)
                    : sprintf('%s>$0</x-slot:%s>', $slotName, $slotName);

                $items[] = new CompletionItem(
                    $label,
                    null,
                    CompletionItemKind::PROPERTY,
                    null,
                    'Blade named slot',
                    null,
                    null,
                    null,
                    $label,
                    $label,
                    $insertText,
                    InsertTextFormat::SNIPPET
                );
            }

            if ($style === 'legacy' || $style === 'both') {
                $label = sprintf('name="%s"', $slotName);

                $items[] = new CompletionItem(
                    $label,
                    null,
                    CompletionItemKind::PROPERTY,
                    null,
                    'Blade named slot',
                    null,
                    null,
                    null,
                    $slotName,
                    $slotName,
                    sprintf('%s">$0</x-slot>', $slotName),
                    InsertTextFormat::SNIPPET
                );
            }
        }

        return $items;
    }

    private function normalizeSlotPrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        $prefix = preg_replace('/["\'"“”‘’]/u', '', $prefix) ?? $prefix;

        return trim($prefix);
    }

    private function activeComponentNameFromPrefix(string $documentPrefix): ?string
    {
        $result = preg_match_all('/<\/?x-([A-Za-z0-9_\-.:]+)([^>]*)>/', $documentPrefix, $matches, PREG_SET_ORDER);

        if ($result === false || $result === 0) {
            return null;
        }

        if (!isset($matches) || !is_array($matches)) {
            return null;
        }

        $stack = [];

        foreach ($matches as $match) {
            $fullTag = $match[0] ?? '';
            $name = strtolower($match[1] ?? '');
            $tail = $match[2] ?? '';

            if ($name === '' || str_starts_with($fullTag, '<x-slot')) {
                continue;
            }

            $isClosing = str_starts_with($fullTag, '</');
            $isSelfClosing = preg_match('/\/\s*$/', $tail) === 1;

            if ($isClosing) {
                while ($stack !== []) {
                    $top = array_pop($stack);

                    if ($top === $name) {
                        break;
                    }
                }

                continue;
            }

            if (!$isSelfClosing) {
                $stack[] = $name;
            }
        }

        if ($stack === []) {
            return null;
        }

        return $stack[count($stack) - 1];
    }

    private function componentNameFromNodeContext(?object $node, bool $excludeSlotComponents): ?string
    {
        if (!$node instanceof Node) {
            return null;
        }

        $current = $node;

        while ($current instanceof Node) {
            if ($current instanceof ElementNode && $current->isComponent()) {
                $tagName = strtolower($current->tagNameText());

                if (!str_starts_with($tagName, 'x-')) {
                    $current = $current->getParent();
                    continue;
                }

                $componentName = substr($tagName, 2);

                if ($componentName === '') {
                    $current = $current->getParent();
                    continue;
                }

                if ($excludeSlotComponents && (str_starts_with($componentName, 'slot') || str_starts_with($componentName, 'slot:'))) {
                    $current = $current->getParent();
                    continue;
                }

                return $componentName;
            }

            $current = $current->getParent();
        }

        return null;
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

    private function linePrefixFromText(string $text, int $line, int $character): ?string
    {
        $lines = preg_split('/\R/u', $text);
        if ($lines === false || !isset($lines[$line])) {
            return null;
        }

        return substr($lines[$line], 0, $character);
    }

    private function documentPrefixFromText(string $text, int $line, int $character): ?string
    {
        $lines = preg_split('/\R/u', $text);

        if ($lines === false || !isset($lines[$line])) {
            return null;
        }

        $prefixLines = array_slice($lines, 0, $line + 1);
        $prefixLines[$line] = substr($prefixLines[$line], 0, $character);

        return implode("\n", $prefixLines);
    }
}
