<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Handler;

use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Event\TextDocumentClosed;
use Phpactor\LanguageServer\Event\TextDocumentOpened;
use Phpactor\LanguageServer\Event\TextDocumentSaved;
use Phpactor\LanguageServer\Event\TextDocumentUpdated;
use Phpactor\LanguageServerProtocol\DidChangeTextDocumentParams;
use Phpactor\LanguageServerProtocol\DidCloseTextDocumentParams;
use Phpactor\LanguageServerProtocol\DidOpenTextDocumentParams;
use Phpactor\LanguageServerProtocol\DidSaveTextDocumentParams;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\TextDocumentContentChangeIncrementalEvent;
use Phpactor\LanguageServerProtocol\TextDocumentSyncKind;
use Phpactor\LanguageServerProtocol\WillSaveTextDocumentParams;
use Psr\EventDispatcher\EventDispatcherInterface;

final class TextDocumentSyncHandler implements CanRegisterCapabilities, Handler
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Workspace $workspace,
    ) {}

    public function methods(): array
    {
        return [
            'textDocument/didOpen' => 'didOpen',
            'textDocument/didChange' => 'didChange',
            'textDocument/didClose' => 'didClose',
            'textDocument/didSave' => 'didSave',
            'textDocument/willSave' => 'willSave',
            'textDocument/willSaveWaitUntil' => 'willSaveWaitUntil',
        ];
    }

    public function didOpen(DidOpenTextDocumentParams $params): void
    {
        $this->dispatcher->dispatch(new TextDocumentOpened($params->textDocument));
    }

    public function didChange(DidChangeTextDocumentParams $params): void
    {
        $uri = $params->textDocument->uri;
        $text = $this->workspace->has($uri)
            ? $this->workspace->get($uri)->text
            : '';

        foreach ($params->contentChanges as $contentChange) {
            if ($contentChange instanceof TextDocumentContentChangeIncrementalEvent) {
                $text = $this->applyIncrementalChange($text, $contentChange->range, $contentChange->text);

                continue;
            }

            if (is_object($contentChange) && isset($contentChange->range, $contentChange->text) && $contentChange->range instanceof Range) {
                /** @var object{range: Range, text: string} $contentChange */
                $text = $this->applyIncrementalChange($text, $contentChange->range, (string) $contentChange->text);

                continue;
            }

            if (is_object($contentChange) && property_exists($contentChange, 'text')) {
                $text = (string) $contentChange->text;

                continue;
            }

            if (is_array($contentChange) && isset($contentChange['range'], $contentChange['text']) && $contentChange['range'] instanceof Range) {
                $text = $this->applyIncrementalChange($text, $contentChange['range'], (string) $contentChange['text']);

                continue;
            }

            if (is_array($contentChange) && array_key_exists('text', $contentChange)) {
                $text = (string) $contentChange['text'];
            }
        }

        $this->dispatcher->dispatch(new TextDocumentUpdated($params->textDocument, $text));
    }

    public function didClose(DidCloseTextDocumentParams $params): void
    {
        $this->dispatcher->dispatch(new TextDocumentClosed($params->textDocument));
    }

    public function didSave(DidSaveTextDocumentParams $params): void
    {
        $this->dispatcher->dispatch(new TextDocumentSaved($params->textDocument, $params->text));
    }

    public function willSave(WillSaveTextDocumentParams $params): void {}

    public function willSaveWaitUntil(WillSaveTextDocumentParams $params): void {}

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->textDocumentSync = TextDocumentSyncKind::INCREMENTAL;
    }

    private function applyIncrementalChange(string $text, Range $range, string $replacement): string
    {
        $startOffset = $this->offsetForPosition($text, $range->start);
        $endOffset = $this->offsetForPosition($text, $range->end);

        if ($endOffset < $startOffset) {
            $endOffset = $startOffset;
        }

        return substr($text, 0, $startOffset).$replacement.substr($text, $endOffset);
    }

    private function offsetForPosition(string $text, Position $position): int
    {
        $line = max(0, $position->line);
        $character = max(0, $position->character);

        $offset = 0;
        $currentLine = 0;
        $length = strlen($text);

        while ($currentLine < $line && $offset < $length) {
            $nextNewline = strpos($text, "\n", $offset);

            if ($nextNewline === false) {
                return $length;
            }

            $offset = $nextNewline + 1;
            $currentLine++;
        }

        return min($offset + $character, $length);
    }
}
