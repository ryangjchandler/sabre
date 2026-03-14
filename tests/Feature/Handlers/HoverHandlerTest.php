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

test('hover on anonymous component shows component metadata', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['variant' => 'info', 'dismissible' => false])
<div>
    {{ $title }}
    {{ $slot }}
</div>
BLADE,
        'resources/views/components/alert.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-ale[[cursor]]rt />
BLADE,
        'resources/views/hover-component-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);
    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $usage);

    expect($hover)->not->toBeNull();
    expect($hover->contents)->toBeInstanceOf(MarkupContent::class);
    expect($hover->contents->value)->toContain('### `x-alert`');
    expect($hover->contents->value)->toContain('**Path**: [`resources/views/components/alert.blade.php`](file://');
    expect($hover->contents->value)->toContain('#### Props');
    expect($hover->contents->value)->toContain('**Optional**');
    expect($hover->contents->value)->toContain('`variant`');
    expect($hover->contents->value)->toContain('default `\'info\'`');
    expect($hover->contents->value)->toContain('`dismissible`');
    expect($hover->contents->value)->toContain('boolean');
    expect($hover->contents->value)->toContain('#### Slots');
    expect($hover->contents->value)->toContain('**Required**');
    expect($hover->contents->value)->toContain('`title`');
});

test('hover on class component includes class name and class path', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $workspace->put('app/View/Components/AlertBanner.php', <<<'PHP'
<?php
namespace App\View\Components;
use Illuminate\View\Component;
final class AlertBanner extends Component
{
    public function __construct(public string $title) {}
}
PHP
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-alert-ban[[cursor]]ner />
BLADE,
        'resources/views/hover-class-component-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);
    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $usage);

    expect($hover)->not->toBeNull();
    expect($hover->contents->value)->toContain('### `x-alert-banner` (`App\\View\\Components\\AlertBanner`)');
    expect($hover->contents->value)->toContain('**Path**: [`app/View/Components/AlertBanner.php`](file://');
    expect($hover->contents->value)->toContain('`title` (type `string`, source `class`)');
});
