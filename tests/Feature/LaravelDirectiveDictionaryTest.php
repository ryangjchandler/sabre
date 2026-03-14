<?php

declare(strict_types=1);

use RyanChandler\Sabre\Blade\Directives\LaravelDirectiveDictionary;

test('it returns descriptions for known first-party directives', function (): void {
    $dictionary = new LaravelDirectiveDictionary();

    expect($dictionary->descriptionFor('if'))
        ->toBe('Conditionally render content when an expression evaluates to true.');

    expect($dictionary->descriptionFor('@foreach'))
        ->toBe('Start a foreach loop in Blade.');
});

test('it normalizes directive names for lookups', function (): void {
    $dictionary = new LaravelDirectiveDictionary();

    expect($dictionary->descriptionFor('  @EnDaUtH '))
        ->toBe('Close an @auth conditional block.');

    expect($dictionary->has('@JSON'))->toBeTrue();
});

test('it returns null for unknown directives', function (): void {
    $dictionary = new LaravelDirectiveDictionary();

    expect($dictionary->descriptionFor('@myCustomDirective'))->toBeNull();
    expect($dictionary->has('@myCustomDirective'))->toBeFalse();
});
