<?php

declare(strict_types=1);

use Phpactor\LanguageServerProtocol\CompletionOptions;
use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('workspace helper writes readable files from inline strings', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>hello [[cursor]]world</div>',
        'resources/views/readable.blade.php'
    );

    expect(is_file($document->path))->toBeTrue();
    expect(file_get_contents($document->path))->toBe('<div>hello world</div>');
    expect($document->hasCursor())->toBeTrue();
});

test('initialize returns expected baseline capabilities', function (): void {
    $tester = LanguageServerTestHarness::createTester();
    $result = $tester->initialize();

    expect($result->serverInfo)->toMatchArray([
        'name' => 'sabre',
        'version' => '0.1.0',
    ]);

    expect($result->capabilities->hoverProvider)->toBeTrue();
    expect($result->capabilities->completionProvider)->toBeInstanceOf(CompletionOptions::class);
    expect($result->capabilities->executeCommandProvider)->not->toBeNull();
});
