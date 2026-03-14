<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\LanguageServer\Listener;

use Forte\Ast\Document\Document;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Event\TextDocumentClosed;
use Phpactor\LanguageServer\Event\TextDocumentOpened;
use Phpactor\LanguageServer\Event\TextDocumentSaved;
use Phpactor\LanguageServer\Event\TextDocumentUpdated;
use Psr\EventDispatcher\ListenerProviderInterface;
use RuntimeException;
use RyanChandler\Sabre\Blade\Diagnostics\ForteDiagnosticConverter;
use RyanChandler\Sabre\Blade\ForteDocumentParser;

final class BladeDiagnosticsListener implements ListenerProviderInterface
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ClientApi $clientApi,
        private readonly ForteDocumentParser $documentParser,
        private readonly ForteDiagnosticConverter $diagnosticConverter,
    ) {
    }

    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof TextDocumentOpened) {
            yield function (TextDocumentOpened $opened): void {
                if (!$this->isBladeUri($opened->textDocument()->uri)) {
                    return;
                }

                $this->publishForDocument(
                    $opened->textDocument()->uri,
                    $opened->textDocument()->text,
                    $opened->textDocument()->version
                );
            };

            return;
        }

        if ($event instanceof TextDocumentUpdated) {
            yield function (TextDocumentUpdated $updated): void {
                if (!$this->isBladeUri($updated->identifier()->uri)) {
                    return;
                }

                $this->publishForDocument(
                    $updated->identifier()->uri,
                    $updated->updatedText(),
                    $updated->identifier()->version
                );
            };

            return;
        }

        if ($event instanceof TextDocumentSaved) {
            yield function (TextDocumentSaved $saved): void {
                $uri = $saved->identifier()->uri;

                if (!$this->isBladeUri($uri)) {
                    return;
                }

                if ($this->workspace->has($uri)) {
                    $item = $this->workspace->get($uri);

                    $this->publishForDocument($uri, $item->text, $item->version);

                    return;
                }

                try {
                    $document = $this->documentParser->parseUri($uri);
                } catch (RuntimeException) {
                    return;
                }

                $this->publishFromParsedDocument($uri, $document, null);
            };

            return;
        }

        if ($event instanceof TextDocumentClosed) {
            yield function (TextDocumentClosed $closed): void {
                if (!$this->isBladeUri($closed->identifier()->uri)) {
                    return;
                }

                $this->clientApi->diagnostics()->publishDiagnostics($closed->identifier()->uri, null, []);
            };
        }
    }

    private function isBladeUri(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return false;
        }

        return str_ends_with(strtolower(rawurldecode($path)), '.blade.php');
    }

    private function publishForDocument(string $uri, string $text, ?int $version): void
    {
        try {
            $path = $this->documentParser->uriToPath($uri);
            $document = $this->documentParser->parse($text, $path);
        } catch (RuntimeException) {
            $document = $this->documentParser->parse($text);
        }

        $this->publishFromParsedDocument($uri, $document, $version);
    }

    private function publishFromParsedDocument(string $uri, Document $document, ?int $version): void
    {
        $diagnostics = [];

        foreach ($document->diagnostics() as $diagnostic) {
            $diagnostics[] = $this->diagnosticConverter->toLsp($document, $diagnostic);
        }

        $this->clientApi->diagnostics()->publishDiagnostics($uri, $version, $diagnostics);
    }
}
