<?php

declare(strict_types=1);

use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('go to definition resolves anonymous components to Blade files', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $component = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>Alert component</div>',
        'resources/views/components/alert.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "<div>\n<x-ale[[cursor]]rt />\n</div>",
        'resources/views/definition-anonymous-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);
    $definition = LanguageServerTestHarness::requestDefinitionAtCursor($tester, $usage);

    expect($definition)->not->toBeNull();
    expect($definition->uri)->toBe($component->uri);
});

test('go to definition resolves class-based components to class files', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $classComponent = $workspace->put('app/View/Components/AlertBanner.php', <<<'PHP'
<?php
namespace App\View\Components;
use Illuminate\View\Component;
final class AlertBanner extends Component {}
PHP
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "<div>\n<x-alert-ban[[cursor]]ner />\n</div>",
        'resources/views/definition-class-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);
    $definition = LanguageServerTestHarness::requestDefinitionAtCursor($tester, $usage);

    expect($definition)->not->toBeNull();
    expect($definition->uri)->toBe($classComponent->uri);
});
