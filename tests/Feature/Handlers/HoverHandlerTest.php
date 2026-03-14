<?php

declare(strict_types=1);

use Phpactor\LanguageServerProtocol\MarkupContent;
use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;
use RyanChandler\Sabre\Tests\Support\TestDocument;

test('hover request returns directive details at cursor', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "@if[[cursor]](\$user)\n<p>Hello {{ \$user->name }}</p>\n@endif",
        'resources/views/hover-directive.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $document);

    expect($hover)->not->toBeNull();
    expect($hover->contents)->toBeInstanceOf(MarkupContent::class);
    expect($hover->contents->value)->toContain('Blade directive `@if`');
});

test('hover returns null for non-directive nodes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "<div>\n{{ [[cursor]]\$name }}\n</div>",
        'resources/views/hover-echo.blade.php'
    );

    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $document);
    expect($hover)->toBeNull();
});

test('hover reflects latest text after incremental document changes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "@if(\$first)\nHello\n@endif",
        'resources/views/hover-incremental.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    LanguageServerTestHarness::updateDocumentIncrementally($tester, $document->uri, 2, 0, 5, 0, 10, 'second');

    $updated = new TestDocument(
        $document->relativePath,
        $document->path,
        $document->uri,
        "@if(\$second)\nHello\n@endif",
        0,
        1
    );

    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $updated);
    expect($hover)->not->toBeNull();
    expect($hover->contents->value)->toContain('@if($second)');
});
