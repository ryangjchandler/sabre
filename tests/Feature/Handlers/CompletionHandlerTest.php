<?php

declare(strict_types=1);

use Phpactor\LanguageServerProtocol\CompletionItem;
use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('completion request at a position returns blade stubs', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    @[[cursor]]
</div>
BLADE,
        'resources/views/welcome.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('@if', '@foreach', '@include', '@forelse');
});

test('completion items include filter and insert text for typed directive prefixes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    @fo[[cursor]]
</div>
BLADE,
        'resources/views/completion-prefix.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $foreachItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === '@foreach') {
            $foreachItem = $item;
            break;
        }
    }

    expect($foreachItem)->not->toBeNull();
    expect($foreachItem->filterText)->toBe('foreach');
    expect($foreachItem->insertText)->toBe('foreach');
});

test('directive completions are suppressed for escaped directives', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    @@fo[[cursor]]
</div>
BLADE,
        'resources/views/escaped-directive-completion.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->not->toContain('@foreach');
});

test('directive completions are not returned in html tag context', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <[[cursor]]
</div>
BLADE,
        'resources/views/html-tag-context-completion.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->not->toContain('@if');
});

test('component completion resolves anonymous Blade components for x- tags', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument($workspace, '<div>Alert</div>', 'resources/views/components/alert.blade.php');
    LanguageServerTestHarness::createBladeDocument($workspace, '<div>Input</div>', 'resources/views/components/form/input.blade.php');

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-al[[cursor]]
</div>
BLADE,
        'resources/views/component-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(static fn (CompletionItem $item): string => $item->label, $completionList->items);
    expect($labels)->toContain('x-alert', 'x-form.input');
});

test('component completion inserts closing tag for required slots and self-closes optional ones', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument($workspace, "<div>\n{{ \$slot }}\n</div>", 'resources/views/components/panel.blade.php');
    LanguageServerTestHarness::createBladeDocument($workspace, "<footer>\n{{ \$subtitle ?? '' }}\n</footer>", 'resources/views/components/card/footer.blade.php');

    $required = LanguageServerTestHarness::createBladeDocument($workspace, "<x-pan[[cursor]]", 'resources/views/required-slot.blade.php');
    $optional = LanguageServerTestHarness::createBladeDocument($workspace, "<x-card.foo[[cursor]]", 'resources/views/optional-slot.blade.php');

    LanguageServerTestHarness::openTestDocument($tester, $required);
    $requiredList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $required);

    LanguageServerTestHarness::openTestDocument($tester, $optional);
    $optionalList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $optional);

    $requiredItem = null;
    foreach ($requiredList->items as $item) {
        if ($item->label === 'x-panel') {
            $requiredItem = $item;
            break;
        }
    }

    $optionalItem = null;
    foreach ($optionalList->items as $item) {
        if ($item->label === 'x-card.footer') {
            $optionalItem = $item;
            break;
        }
    }

    expect($requiredItem)->not->toBeNull();
    expect($requiredItem->insertText)->toBe('panel>$0</x-panel>');
    expect($optionalItem)->not->toBeNull();
    expect($optionalItem->insertText)->toBe('card.footer />');
});

test('component attribute completion supports anonymous and class-based components', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['variant' => 'info', 'dismissible' => false])
<div {{ $attributes }}>{{ $slot }}</div>
BLADE,
        'resources/views/components/alert.blade.php'
    );

    $workspace->put('app/View/Components/Alert.php', <<<'PHP'
<?php
namespace App\View\Components;
use Illuminate\View\Component;
final class Alert extends Component { public function __construct(public ?string $iconName = null) {} }
PHP
    );

    $anon = LanguageServerTestHarness::createBladeDocument($workspace, '<x-alert di[[cursor]]', 'resources/views/anon-attrs.blade.php');
    $bound = LanguageServerTestHarness::createBladeDocument($workspace, '<x-alert :v[[cursor]]', 'resources/views/bound-attrs.blade.php');
    $class = LanguageServerTestHarness::createBladeDocument($workspace, '<x-alert ic[[cursor]]', 'resources/views/class-attrs.blade.php');

    LanguageServerTestHarness::openTestDocument($tester, $anon);
    $anonList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $anon);
    LanguageServerTestHarness::openTestDocument($tester, $bound);
    $boundList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $bound);
    LanguageServerTestHarness::openTestDocument($tester, $class);
    $classList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $class);

    $anonLabels = array_map(static fn (CompletionItem $item): string => $item->label, $anonList->items);
    expect($anonLabels)->toContain('dismissible');

    $boundVariant = null;
    foreach ($boundList->items as $item) {
        if ($item->label === ':variant') {
            $boundVariant = $item;
            break;
        }
    }
    expect($boundVariant)->not->toBeNull();
    expect($boundVariant->insertText)->toBe(':variant="$1"');

    $classLabels = array_map(static fn (CompletionItem $item): string => $item->label, $classList->items);
    expect($classLabels)->toContain('icon-name');
});

test('slot completion supports shorthand and legacy syntaxes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument($workspace, "<section>\n{{ \$title }}\n</section>", 'resources/views/components/panel.blade.php');

    $shorthand = LanguageServerTestHarness::createBladeDocument($workspace, "<x-panel>\n<x-slot:ti[[cursor]]\n</x-panel>", 'resources/views/slot-shorthand.blade.php');
    $legacy = LanguageServerTestHarness::createBladeDocument($workspace, "<x-panel>\n<x-slot name=ti[[cursor]]\n</x-panel>", 'resources/views/slot-legacy.blade.php');

    LanguageServerTestHarness::openTestDocument($tester, $shorthand);
    $shortList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $shorthand);
    LanguageServerTestHarness::openTestDocument($tester, $legacy);
    $legacyList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $legacy);

    $shortLabels = array_map(static fn (CompletionItem $item): string => $item->label, $shortList->items);
    $legacyLabels = array_map(static fn (CompletionItem $item): string => $item->label, $legacyList->items);

    expect($shortLabels)->toContain('x-slot:title');
    expect($legacyLabels)->toContain('name="title"');
});
