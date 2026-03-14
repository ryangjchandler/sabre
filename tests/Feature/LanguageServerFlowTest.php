<?php

declare(strict_types=1);

use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\MarkupContent;
use RyanChandler\Sabre\Tests\Support\LanguageServerTestHarness;

test('workspace helper writes readable files from inline strings', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        "<div>hello [[cursor]]world</div>",
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

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>Alert</div>',
        'resources/views/components/alert.blade.php'
    );

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '<div>Input</div>',
        'resources/views/components/form/input.blade.php'
    );

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

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('x-alert', 'x-form.input');

    $alertItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-alert') {
            $alertItem = $item;
            break;
        }
    }

    expect($alertItem)->not->toBeNull();
    expect($alertItem->filterText)->toBe('x-alert');
    expect($alertItem->insertText)->toBe('alert />');
});

test('component completion inserts closing tag for components with required default slot', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    {{ $slot }}
</div>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-pan[[cursor]]
</div>
BLADE,
        'resources/views/component-slot-completion.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $panelItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-panel') {
            $panelItem = $item;
            break;
        }
    }

    expect($panelItem)->not->toBeNull();
    expect($panelItem->insertText)->toBe('panel>$0</x-panel>');
});

test('component completion inserts closing tag for required named slots', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<header>
    {{ $title }}
</header>
BLADE,
        'resources/views/components/card/header.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-card.hea[[cursor]]
</div>
BLADE,
        'resources/views/component-named-slot-completion.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $headerItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-card.header') {
            $headerItem = $item;
            break;
        }
    }

    expect($headerItem)->not->toBeNull();
    expect($headerItem->insertText)->toBe('card.header>$0</x-card.header>');
});

test('component completion self-closes when named slot usage is optional', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<footer>
    {{ $subtitle ?? '' }}
</footer>
BLADE,
        'resources/views/components/card/footer.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-card.foo[[cursor]]
</div>
BLADE,
        'resources/views/component-optional-named-slot-completion.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $footerItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-card.footer') {
            $footerItem = $item;
            break;
        }
    }

    expect($footerItem)->not->toBeNull();
    expect($footerItem->insertText)->toBe('card.footer />');
});

test('slot completion supports shorthand x-slot:name syntax', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<section>
    {{ $title }}
</section>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-panel>
    <x-slot:ti[[cursor]]
</x-panel>
BLADE,
        'resources/views/slot-shorthand-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $usage);

    $slotItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-slot:title') {
            $slotItem = $item;
            break;
        }
    }

    expect($slotItem)->not->toBeNull();
    expect($slotItem->insertText)->toBe('title>$0</x-slot:title>');
});

test('slot completion supports legacy x-slot name syntax', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<section>
    {{ $title }}
</section>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-panel>
    <x-slot name="ti[[cursor]]
</x-panel>
BLADE,
        'resources/views/slot-legacy-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $usage);

    $slotItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'name="title"') {
            $slotItem = $item;
            break;
        }
    }

    expect($slotItem)->not->toBeNull();
    expect($slotItem->insertText)->toBe('title">$0</x-slot>');
});

test('slot completion supports legacy x-slot name syntax without quotes yet', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<section>
    {{ $title }}
</section>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-panel>
    <x-slot name=ti[[cursor]]
</x-panel>
BLADE,
        'resources/views/slot-legacy-unquoted-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $usage);

    $slotItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'name="title"') {
            $slotItem = $item;
            break;
        }
    }

    expect($slotItem)->not->toBeNull();
});

test('slot completion suggests both slot syntaxes when typing bare x-slot', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<section>
    {{ $title }}
</section>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-panel>
    <x-slot[[cursor]]
</x-panel>
BLADE,
        'resources/views/slot-bare-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $usage);
    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('x-slot:title', 'name="title"');

    $shorthandItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'x-slot:title') {
            $shorthandItem = $item;
            break;
        }
    }

    expect($shorthandItem)->not->toBeNull();
    expect($shorthandItem->insertText)->toBe('x-slot:title>$0</x-slot:title>');
});

test('slot completion returns no suggestions when component has no named slots', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['title' => null])

<section>
    {{ $slot }}
</section>
BLADE,
        'resources/views/components/panel.blade.php'
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<x-panel>
    <x-slot[[cursor]]
</x-panel>
BLADE,
        'resources/views/slot-generic-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $usage);
    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toHaveCount(0);
});

test('component attribute completion uses component props and common attributes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['variant' => 'info', 'dismissible' => false])

<div {{ $attributes }}>
    {{ $slot }}
</div>
BLADE,
        'resources/views/components/alert.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-alert di[[cursor]]
</div>
BLADE,
        'resources/views/component-attribute-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('dismissible');
    expect($labels)->not->toContain('variant');

    $dismissibleItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'dismissible') {
            $dismissibleItem = $item;
            break;
        }
    }

    expect($dismissibleItem)->not->toBeNull();
    expect($dismissibleItem->insertText)->toBe('dismissible');
});

test('component attribute completion offers bound variants and value snippets for non-boolean props', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['variant' => 'info', 'dismissible' => false])

<div {{ $attributes }}>
    {{ $slot }}
</div>
BLADE,
        'resources/views/components/alert.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-alert :v[[cursor]]
</div>
BLADE,
        'resources/views/component-attribute-bound-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $boundVariant = null;
    foreach ($completionList->items as $item) {
        if ($item->label === ':variant') {
            $boundVariant = $item;
            break;
        }
    }

    expect($boundVariant)->not->toBeNull();
    expect($boundVariant->insertText)->toBe(':variant="$1"');

});

test('bound boolean attributes also insert with value snippets', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['variant' => 'info', 'dismissible' => false])

<div {{ $attributes }}>
    {{ $slot }}
</div>
BLADE,
        'resources/views/components/alert.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-alert :di[[cursor]]
</div>
BLADE,
        'resources/views/component-attribute-bound-boolean-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $boundDismissible = null;
    foreach ($completionList->items as $item) {
        if ($item->label === ':dismissible') {
            $boundDismissible = $item;
            break;
        }
    }

    expect($boundDismissible)->not->toBeNull();
    expect($boundDismissible->insertText)->toBe(':dismissible="$1"');
});

test('component attribute completion supports class-based components', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $workspace->put(
        'app/View/Components/Alert.php',
        <<<'PHP'
<?php

namespace App\View\Components;

use Illuminate\View\Component;

final class Alert extends Component
{
    public function __construct(
        public string $type,
        public bool $dismissible = false,
        public ?string $iconName = null,
    ) {
    }
}
PHP
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-alert ic[[cursor]]
</div>
BLADE,
        'resources/views/class-component-attribute-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('icon-name');
    expect($labels)->not->toContain('dismissible');

    $iconItem = null;
    foreach ($completionList->items as $item) {
        if ($item->label === 'icon-name') {
            $iconItem = $item;
            break;
        }
    }

    expect($iconItem)->not->toBeNull();
    expect($iconItem->insertText)->toBe('icon-name="$1"');
});

test('component attribute completion works for dotted anonymous component names', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@props(['name', 'label' => null])

<input name="{{ $name }}" {{ $attributes }} />
BLADE,
        'resources/views/components/form/input.blade.php'
    );

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-form.input la[[cursor]] />
</div>
BLADE,
        'resources/views/dotted-component-attribute-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $completionList = LanguageServerTestHarness::requestCompletionAtCursor($tester, $document);

    $labels = array_map(
        static fn (CompletionItem $item): string => $item->label,
        $completionList->items
    );

    expect($labels)->toContain('label');
});

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
        <<<'BLADE'
<div>
    <x-ale[[cursor]]rt />
</div>
BLADE,
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

    $classComponent = $workspace->put(
        'app/View/Components/AlertBanner.php',
        <<<'PHP'
<?php

namespace App\View\Components;

use Illuminate\View\Component;

final class AlertBanner extends Component
{
}
PHP
    );

    $usage = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    <x-alert-ban[[cursor]]ner />
</div>
BLADE,
        'resources/views/definition-class-usage.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $usage);

    $definition = LanguageServerTestHarness::requestDefinitionAtCursor($tester, $usage);

    expect($definition)->not->toBeNull();
    expect($definition->uri)->toBe($classComponent->uri);
});

test('hover request returns directive details at cursor', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
@if[[cursor]]($user)
    <p>Hello {{ $user->name }}</p>
@endif
BLADE,
        'resources/views/hover-directive.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $document);

    expect($hover)->not->toBeNull();
    expect($hover->contents)->toBeInstanceOf(MarkupContent::class);
    expect($hover->contents->value)->toContain('Blade directive `@if`');
    expect($hover->contents->value)->toContain('Conditionally render content when an expression evaluates to true.');
    expect($hover->range?->start->line)->toBe(0);
    expect($hover->range?->start->character)->toBe(0);
    expect($hover->range?->end->line)->toBe(0);
    expect($hover->range?->end->character)->toBe(3);
});

test('hover returns null for non-directive nodes', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        <<<'BLADE'
<div>
    {{ [[cursor]]$name }}
</div>
BLADE,
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
        <<<'BLADE'
@if($first)
    Hello
@endif
BLADE,
        'resources/views/hover-incremental.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    LanguageServerTestHarness::updateDocumentIncrementally(
        $tester,
        $document->uri,
        2,
        0,
        5,
        0,
        10,
        'second'
    );

    $updatedDocument = new \RyanChandler\Sabre\Tests\Support\TestDocument(
        $document->relativePath,
        $document->path,
        $document->uri,
        "@if(\$second)\n    Hello\n@endif",
        0,
        1
    );

    $hover = LanguageServerTestHarness::requestHoverAtCursor($tester, $updatedDocument);

    expect($hover)->not->toBeNull();
    expect($hover->contents)->toBeInstanceOf(MarkupContent::class);
    expect($hover->contents->value)->toContain('@if($second)');
});

test('diagnostics can be asserted through outgoing client notifications', function (): void {
    $tester = LanguageServerTestHarness::createTester();
    LanguageServerTestHarness::initialize($tester);

    $uri = 'file:///workspace/resources/views/welcome.blade.php';
    $diagnosticPayload = [
        [
            'range' => [
                'start' => ['line' => 0, 'character' => 0],
                'end' => ['line' => 0, 'character' => 3],
            ],
            'severity' => 1,
            'message' => 'Stub diagnostic for integration testing.',
            'source' => 'sabre-test',
        ],
    ];

    LanguageServerTestHarness::publishDiagnostics($tester, $uri, $diagnosticPayload, 1);

    $published = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($published)->toHaveCount(1);
    expect($published[0]->uri)->toBe($uri);
    expect($published[0]->diagnostics)->toHaveCount(1);
    expect($published[0]->diagnostics[0]->message)->toBe('Stub diagnostic for integration testing.');
});

test('opening invalid Blade publishes Forte diagnostics', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '{{',
        'resources/views/invalid.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $published = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($published)->toHaveCount(1);
    expect($published[0]->uri)->toBe($document->uri);
    expect($published[0]->diagnostics)->toHaveCount(1);
    expect($published[0]->diagnostics[0]->message)->toContain('UnexpectedEof');
    expect($published[0]->diagnostics[0]->source)->toBe('sabre.lexer');
});

test('diagnostics are ignored for non-blade files', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = $workspace->put(
        'resources/views/not-blade.php',
        '{{'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);

    $published = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($published)->toHaveCount(0);

    LanguageServerTestHarness::closeDocument($tester, $document->uri);

    $publishedOnClose = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($publishedOnClose)->toHaveCount(0);
});

test('closing a document publishes empty diagnostics', function (): void {
    $workspace = LanguageServerTestHarness::createWorkspace();
    $tester = LanguageServerTestHarness::createTester($workspace);
    LanguageServerTestHarness::initialize($tester);

    $document = LanguageServerTestHarness::createBladeDocument(
        $workspace,
        '{{',
        'resources/views/close-clears-diagnostics.blade.php'
    );

    LanguageServerTestHarness::openTestDocument($tester, $document);
    LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    LanguageServerTestHarness::closeDocument($tester, $document->uri);

    $publishedOnClose = LanguageServerTestHarness::drainPublishedDiagnostics($tester);

    expect($publishedOnClose)->toHaveCount(1);
    expect($publishedOnClose[0]->uri)->toBe($document->uri);
    expect($publishedOnClose[0]->diagnostics)->toHaveCount(0);
});
