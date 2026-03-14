<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use RyanChandler\Sabre\Blade\ForteDocumentParser;
use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('it parses a template string and associates a file path', function (): void {
    $parser = new ForteDocumentParser;

    $document = $parser->parse('<div>{{ $name }}</div>', '/tmp/example.blade.php');

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->getFilePath())->toBe('/tmp/example.blade.php');
    expect($document->render())->toBe('<div>{{ $name }}</div>');
});

test('it parses a Blade file from disk and returns a Forte document', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $file = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "<x-alert type=\"error\">{{ __('Oops') }}</x-alert>",
        'resources/views/components/alert.blade.php'
    );

    $parser = new ForteDocumentParser;
    $document = $parser->parseFile($file->path);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->getFilePath())->toBe($file->path);
    expect($document->render())->toContain('<x-alert');
});

test('it parses multiple files and returns path keyed documents', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();

    $first = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>one</div>',
        'resources/views/one.blade.php'
    );

    $second = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>two</div>',
        'resources/views/two.blade.php'
    );

    $parser = new ForteDocumentParser;
    $documents = $parser->parseFiles([$first->path, $second->path]);

    expect($documents)->toHaveKeys([$first->path, $second->path]);
    expect($documents[$first->path]->render())->toBe('<div>one</div>');
    expect($documents[$second->path]->render())->toBe('<div>two</div>');
});

test('it parses a file URI', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $file = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>uri</div>',
        'resources/views/uri.blade.php'
    );

    $parser = new ForteDocumentParser;
    $document = $parser->parseUri($file->uri);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->getFilePath())->toBe($file->path);
    expect($document->render())->toBe('<div>uri</div>');
});
