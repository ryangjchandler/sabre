<?php

declare(strict_types=1);

use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('diagnostics can be asserted through outgoing client notifications', function (): void {
    $tester = LanguageServerTestHarness::createTester();
    LanguageServerTestHarness::initialize($tester);

    $uri = 'file:///workspace/resources/views/welcome.blade.php';
    $diagnosticPayload = [[
        'range' => ['start' => ['line' => 0, 'character' => 0], 'end' => ['line' => 0, 'character' => 3]],
        'severity' => 1,
        'message' => 'Stub diagnostic for integration testing.',
        'source' => 'sabre-test',
    ]];

    LanguageServerTestHarness::publishDiagnostics($tester, $uri, $diagnosticPayload, 1);
    $published = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($published)->toHaveCount(1);
    expect($published[0]->uri)->toBe($uri);
    expect($published[0]->diagnostics)->toHaveCount(1);
});

test('opening invalid Blade publishes Forte diagnostics', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument($workspace, '{{', 'resources/views/invalid.blade.php');

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $published = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($published)->toHaveCount(1);
    expect($published[0]->uri)->toBe($document->uri);
    expect($published[0]->diagnostics)->toHaveCount(1);
});

test('diagnostics are ignored for non-blade files and cleared on close for blade files', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $nonBlade = $workspace->put('resources/views/not-blade.php', '{{');
    LanguageServerTestHarness::openTestDocument($tester, $nonBlade);
    expect(LanguageServerTestHarness::drainPublishedDiagnostics($tester))->toHaveCount(0);

    $blade = LanguageServerTestHarness::createBladeDocument($workspace, '{{', 'resources/views/close-clears-diagnostics.blade.php');
    LanguageServerTestHarness::openTestDocument($tester, $blade);
    LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    LanguageServerTestHarness::closeDocument($tester, $blade->uri);
    $publishedOnClose = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($publishedOnClose)->toHaveCount(1);
    expect($publishedOnClose[0]->uri)->toBe($blade->uri);
    expect($publishedOnClose[0]->diagnostics)->toHaveCount(0);
});
