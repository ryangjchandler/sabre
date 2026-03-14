<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Blade\Hover;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\MarkupContent;
use Phpactor\LanguageServerProtocol\MarkupKind;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use RyanChandler\Sabre\Blade\Directives\LaravelDirectiveDictionary;

final class ForteHoverProvider
{
    public function __construct(private readonly ?LaravelDirectiveDictionary $directiveDictionary = null)
    {
    }

    public function provide(Document $document, int $line, int $character): ?Hover
    {
        $node = $document->findNodeAtPosition($line + 1, $character + 1);

        if (!$node instanceof DirectiveNode && !$node instanceof DirectiveBlockNode) {
            return null;
        }

        return new Hover(
            new MarkupContent(MarkupKind::MARKDOWN, $this->contentForNode($node)),
            $this->rangeForNode($document, $node)
        );
    }

    private function contentForNode(DirectiveNode|DirectiveBlockNode $node): string
    {
        if ($node instanceof DirectiveBlockNode) {
            $args = $node->arguments();
            $description = $this->directiveDescription($node->nameText());

            return sprintf(
                "Blade directive block `@%s`%s\n\n```blade\n%s\n```",
                $node->nameText(),
                $description === null ? '' : sprintf("\n\n%s", $description),
                $args === null ? sprintf('@%s', $node->nameText()) : sprintf('@%s%s', $node->nameText(), $args)
            );
        }

        if ($node instanceof DirectiveNode) {
            $args = $node->arguments();
            $description = $this->directiveDescription($node->nameText());

            return sprintf(
                "Blade directive `@%s`%s\n\n```blade\n%s\n```",
                $node->nameText(),
                $description === null ? '' : sprintf("\n\n%s", $description),
                $args === null ? sprintf('@%s', $node->nameText()) : sprintf('@%s%s', $node->nameText(), $args)
            );
        }

        return '';
    }

    private function rangeForNode(Document $document, DirectiveNode|DirectiveBlockNode $node): ?Range
    {
        $startOffset = $node->startOffset();
        $endOffset = $startOffset + strlen(sprintf('@%s', $node->name()));

        if ($startOffset < 0 || $endOffset < 0 || $endOffset < $startOffset) {
            return null;
        }

        $start = $document->getLineAndColumnForOffset($startOffset);
        $end = $document->getLineAndColumnForOffset($endOffset);

        return new Range(
            new Position($start['line'] - 1, $start['column'] - 1),
            new Position($end['line'] - 1, $end['column'] - 1)
        );
    }

    private function directiveDescription(string $directive): ?string
    {
        $dictionary = $this->directiveDictionary ?? new LaravelDirectiveDictionary();

        return $dictionary->descriptionFor($directive);
    }
}
