<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Blade\Directives;

final class LaravelDirectiveDictionary
{
    /**
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'auth' => 'Render content only when the current user is authenticated.',
        'endauth' => 'Close an @auth conditional block.',
        'guest' => 'Render content only when the current user is a guest.',
        'endguest' => 'Close an @guest conditional block.',
        'can' => 'Render content when the current user is authorized for an ability.',
        'cannot' => 'Render content when the current user is not authorized for an ability.',
        'canany' => 'Render content when the current user has any listed ability.',
        'endcan' => 'Close an authorization block started by @can/@cannot/@canany.',
        'if' => 'Conditionally render content when an expression evaluates to true.',
        'elseif' => 'Add an additional conditional branch to an @if block.',
        'else' => 'Add a fallback branch to a conditional Blade block.',
        'endif' => 'Close an @if conditional block.',
        'unless' => 'Conditionally render content when an expression evaluates to false.',
        'endunless' => 'Close an @unless conditional block.',
        'isset' => 'Render content when a variable is defined and not null.',
        'endisset' => 'Close an @isset conditional block.',
        'empty' => 'Render content when a value is considered empty.',
        'endempty' => 'Close an @empty conditional block.',
        'switch' => 'Start a switch statement in Blade.',
        'case' => 'Define a switch case branch in an @switch block.',
        'default' => 'Define the default branch in an @switch block.',
        'endswitch' => 'Close an @switch block.',
        'for' => 'Start a for loop in Blade.',
        'endfor' => 'Close a @for loop block.',
        'foreach' => 'Start a foreach loop in Blade.',
        'endforeach' => 'Close a @foreach loop block.',
        'forelse' => 'Start a foreach loop with an empty fallback branch.',
        'emptyforelse' => 'Define the empty branch inside a @forelse loop.',
        'endforelse' => 'Close a @forelse loop block.',
        'while' => 'Start a while loop in Blade.',
        'endwhile' => 'Close a @while loop block.',
        'break' => 'Break out of the nearest loop.',
        'continue' => 'Continue to the next iteration of the nearest loop.',
        'php' => 'Open a raw PHP block within Blade.',
        'endphp' => 'Close a raw PHP block started by @php.',
        'verbatim' => 'Disable Blade parsing for the enclosed content.',
        'endverbatim' => 'Close a @verbatim block.',
        'once' => 'Render the enclosed content only once per request.',
        'endonce' => 'Close an @once block.',
        'include' => 'Include and render another Blade view.',
        'includeif' => 'Include a view only if it exists.',
        'includewhen' => 'Conditionally include a view when a condition is true.',
        'includeunless' => 'Conditionally include a view when a condition is false.',
        'includefirst' => 'Include the first existing view from a list of candidates.',
        'extends' => 'Declare the parent layout that the current view extends.',
        'section' => 'Start a named section that can be yielded in a layout.',
        'endsection' => 'Close a @section block.',
        'show' => 'Close a @section and immediately yield its content.',
        'yield' => 'Output the content of a named section.',
        'hassection' => 'Check whether a named section has content.',
        'stop' => 'Stop and close the current section block.',
        'overwrite' => 'Close the section and replace any existing content.',
        'parent' => 'Insert the parent section content within an overridden section.',
        'append' => 'Close the section and append content to the parent section.',
        'push' => 'Push content onto a named stack.',
        'endpush' => 'Close a @push block.',
        'prepend' => 'Prepend content to a named stack.',
        'endprepend' => 'Close a @prepend block.',
        'stack' => 'Render all content from a named stack.',
        'pushonce' => 'Push content onto a stack once using a unique key.',
        'endpushonce' => 'Close a @pushonce block.',
        'prependonce' => 'Prepend content to a stack once using a unique key.',
        'endprependonce' => 'Close a @prependonce block.',
        'csrf' => 'Render a hidden CSRF token input field.',
        'method' => 'Render a hidden HTTP method spoofing input.',
        'vite' => 'Include assets managed by Vite.',
        'json' => 'Encode a value as JSON output.',
        'class' => 'Conditionally build a CSS class attribute string.',
        'style' => 'Conditionally build an inline style attribute string.',
        'checked' => 'Render checked when the condition evaluates to true.',
        'selected' => 'Render selected when the condition evaluates to true.',
        'disabled' => 'Render disabled when the condition evaluates to true.',
        'readonly' => 'Render readonly when the condition evaluates to true.',
        'required' => 'Render required when the condition evaluates to true.',
        'session' => 'Retrieve and render a value from the session.',
        'error' => 'Conditionally render content when a validation error exists.',
        'enderror' => 'Close an @error block.',
    ];

    public function descriptionFor(string $directive): ?string
    {
        $normalized = $this->normalize($directive);

        return self::DESCRIPTIONS[$normalized] ?? null;
    }

    public function has(string $directive): bool
    {
        return $this->descriptionFor($directive) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::DESCRIPTIONS;
    }

    private function normalize(string $directive): string
    {
        return ltrim(strtolower(trim($directive)), '@');
    }
}
