<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Tests\Support;

use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\CompletionRequest;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\DefinitionRequest;
use Phpactor\LanguageServerProtocol\DidChangeTextDocumentNotification;
use Phpactor\LanguageServerProtocol\DidChangeTextDocumentParams;
use Phpactor\LanguageServerProtocol\DidCloseTextDocumentNotification;
use Phpactor\LanguageServerProtocol\DidCloseTextDocumentParams;
use Phpactor\LanguageServerProtocol\ExecuteCommandParams;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\HoverRequest;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\PublishDiagnosticsParams;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\TextDocumentContentChangeIncrementalEvent;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Psr\Log\NullLogger;
use RyanChandler\Sabre\LanguageServer\SabreDispatcherFactory;
use RuntimeException;

final class LanguageServerTestHarness
{
    public static function createWorkspace(): TestWorkspace
    {
        return TestWorkspace::create();
    }

    public static function createTester(?TestWorkspace $workspace = null): LanguageServerTester
    {
        $builder = LanguageServerBuilder::create(new SabreDispatcherFactory(new NullLogger()));

        return $builder->tester(ProtocolFactory::initializeParams($workspace?->rootUri()));
    }

    public static function createBladeDocument(
        TestWorkspace $workspace,
        string $content,
        string $relativePath = 'resources/views/test.blade.php'
    ): TestDocument {
        return $workspace->put($relativePath, $content);
    }

    public static function initialize(LanguageServerTester $tester): void
    {
        $tester->initialize();
        $tester->transmitter()->clear();
    }

    public static function openDocument(LanguageServerTester $tester, string $uri, string $text): void
    {
        $tester->textDocument()->open($uri, $text);
    }

    public static function openTestDocument(LanguageServerTester $tester, TestDocument $document): void
    {
        self::openDocument($tester, $document->uri, $document->content);
    }

    public static function updateDocument(LanguageServerTester $tester, string $uri, string $text): void
    {
        $tester->textDocument()->update($uri, $text);
    }

    public static function updateDocumentIncrementally(
        LanguageServerTester $tester,
        string $uri,
        int $version,
        int $startLine,
        int $startCharacter,
        int $endLine,
        int $endCharacter,
        string $text
    ): void {
        $tester->notifyAndWait(
            DidChangeTextDocumentNotification::METHOD,
            new DidChangeTextDocumentParams(
                new VersionedTextDocumentIdentifier($version, $uri),
                [
                    new TextDocumentContentChangeIncrementalEvent(
                        new Range(
                            new Position($startLine, $startCharacter),
                            new Position($endLine, $endCharacter)
                        ),
                        $text
                    ),
                ]
            )
        );
    }

    public static function closeDocument(LanguageServerTester $tester, string $uri): void
    {
        $tester->notifyAndWait(
            DidCloseTextDocumentNotification::METHOD,
            new DidCloseTextDocumentParams(new TextDocumentIdentifier($uri))
        );
    }

    /**
     * @return list<PublishDiagnosticsParams>
     */
    public static function drainPublishedDiagnostics(LanguageServerTester $tester): array
    {
        $messages = $tester->transmitter()->filterByMethod('textDocument/publishDiagnostics');
        $diagnostics = [];

        while ($message = $messages->shiftNotification()) {
            if (!is_array($message->params)) {
                continue;
            }

            $diagnostics[] = PublishDiagnosticsParams::fromArray($message->params);
        }

        $tester->transmitter()->clear();

        return $diagnostics;
    }

    public static function requestCompletion(
        LanguageServerTester $tester,
        string $uri,
        int $line,
        int $character
    ): CompletionList {
        $response = $tester->mustRequestAndWait(
            CompletionRequest::METHOD,
            new CompletionParams(
                new TextDocumentIdentifier($uri),
                new Position($line, $character)
            )
        );

        $result = $response->result;

        if (!$result instanceof CompletionList) {
            throw new RuntimeException('Expected completion result to be an instance of CompletionList.');
        }

        return $result;
    }

    public static function requestCompletionAtCursor(LanguageServerTester $tester, TestDocument $document): CompletionList
    {
        if (!$document->hasCursor()) {
            throw new RuntimeException('Document has no cursor marker. Add [[cursor]] to your test content.');
        }

        return self::requestCompletion(
            $tester,
            $document->uri,
            $document->cursorLine,
            $document->cursorCharacter
        );
    }

    public static function requestHover(
        LanguageServerTester $tester,
        string $uri,
        int $line,
        int $character
    ): ?Hover {
        $response = $tester->mustRequestAndWait(
            HoverRequest::METHOD,
            new HoverParams(
                new TextDocumentIdentifier($uri),
                new Position($line, $character)
            )
        );

        $result = $response->result;

        if ($result === null) {
            return null;
        }

        if (!$result instanceof Hover) {
            throw new RuntimeException('Expected hover result to be an instance of Hover or null.');
        }

        return $result;
    }

    public static function requestHoverAtCursor(LanguageServerTester $tester, TestDocument $document): ?Hover
    {
        if (!$document->hasCursor()) {
            throw new RuntimeException('Document has no cursor marker. Add [[cursor]] to your test content.');
        }

        return self::requestHover(
            $tester,
            $document->uri,
            $document->cursorLine,
            $document->cursorCharacter
        );
    }

    public static function requestDefinition(
        LanguageServerTester $tester,
        string $uri,
        int $line,
        int $character
    ): ?Location {
        $response = $tester->mustRequestAndWait(
            DefinitionRequest::METHOD,
            new DefinitionParams(
                new TextDocumentIdentifier($uri),
                new Position($line, $character)
            )
        );

        $result = $response->result;

        if ($result === null) {
            return null;
        }

        if (!$result instanceof Location) {
            throw new RuntimeException('Expected definition result to be an instance of Location or null.');
        }

        return $result;
    }

    public static function requestDefinitionAtCursor(LanguageServerTester $tester, TestDocument $document): ?Location
    {
        if (!$document->hasCursor()) {
            throw new RuntimeException('Document has no cursor marker. Add [[cursor]] to your test content.');
        }

        return self::requestDefinition(
            $tester,
            $document->uri,
            $document->cursorLine,
            $document->cursorCharacter
        );
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    public static function publishDiagnostics(
        LanguageServerTester $tester,
        string $uri,
        array $diagnostics,
        int $version = 1
    ): void {
        $response = $tester->mustRequestAndWait(
            'workspace/executeCommand',
            new ExecuteCommandParams('sabre.debug.publishDiagnostics', [$uri, $diagnostics, $version])
        );

        $tester->assertSuccess($response);
    }
}
