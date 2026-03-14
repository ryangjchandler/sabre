# AGENTS.md
Guide for coding agents working in this repository.

## Repository Facts
- Package: `ryangjchandler/sabre`
- Type: Composer library
- Purpose: language server for Laravel Blade (from `composer.json`)
- PSR-4 autoload: `RyanChandler\Sabre\` => `src/`
- Current state: early scaffold, minimal committed code/tooling

## Instruction Priority
1. Direct user request
2. Cursor/Copilot rule files (if present)
3. Checked-in tool config files (`phpunit`, `phpstan`, `phpcs`, etc.)
4. This `AGENTS.md`
5. Ecosystem defaults (PSR-12, PHPUnit conventions)

If config files conflict with this guide, follow config files.

## Current Tooling Discovery
At generation time, repository scan found:
- `composer.json` present
- `phpunit.xml` present
- `tests/Pest.php` present
- Pest configured via `vendor/bin/pest` and Composer scripts
- no `phpstan*.neon*` or `psalm*.xml*`
- no `phpcs.xml*`, `.php-cs-fixer*`, or Pint config
- no CI workflow files

## Commands You Can Run Now
- Install dependencies: `composer install`
- Refresh autoload map: `composer dump-autoload`
- Run full tests: `composer test` or `vendor/bin/pest`
- Run a single Pest file: `vendor/bin/pest tests/Feature/LanguageServerFlowTest.php`
- Run a single test by name: `vendor/bin/pest --filter="completion request at a position returns blade stubs"`

There is no dedicated build command currently.

## Build / Lint / Test Command Matrix

### Build
- Preferred when added: `composer run build`
- Current fallback: `composer dump-autoload`

### Lint / Format (run only configured tool)
- Pint: `vendor/bin/pint`
- PHP CS Fixer: `vendor/bin/php-cs-fixer fix`
- PHPCS: `vendor/bin/phpcs`

### Tests (if framework exists)
PHPUnit:
- Full suite: `vendor/bin/phpunit`
- Single file: `vendor/bin/phpunit tests/Unit/ExampleTest.php`
- Single method: `vendor/bin/phpunit --filter test_example`
- Class method: `vendor/bin/phpunit --filter ExampleTest::test_example`

Pest:
- Full suite: `vendor/bin/pest`
- Single file: `vendor/bin/pest tests/Unit/ExampleTest.php`
- Single test name: `vendor/bin/pest --filter="example"`

### Static Analysis (if configured)
PHPStan:
- Full: `vendor/bin/phpstan analyse`
- Single path: `vendor/bin/phpstan analyse src/Path/File.php`

Psalm:
- Full: `vendor/bin/psalm`
- Single path: `vendor/bin/psalm src/Path/File.php`

## Single-Test Execution (Important)
Use the narrowest command first.

Decision order:
1. If `vendor/bin/pest` exists, use Pest.
2. Else if `vendor/bin/phpunit` exists, use PHPUnit.
3. Else report: tests are not configured yet.

Most reliable single-test forms:
- By file: `vendor/bin/phpunit tests/.../FooTest.php`
- By file: `vendor/bin/pest tests/.../FooTest.php`
- By filter: `vendor/bin/phpunit --filter test_foo`
- By filter: `vendor/bin/pest --filter="foo"`

## Code Style Rules
No formatter is committed yet, so follow conservative PHP standards.

### Formatting
- Use `<?php` and `declare(strict_types=1);` in new source files.
- Follow PSR-12 style unless project config later overrides it.
- Use 4 spaces, not tabs.
- Prefer early returns over deeply nested conditionals.

### Namespace and Imports
- Namespace must mirror `src/` path using PSR-4.
- Example: `src/Foo/Bar.php` => `namespace RyanChandler\Sabre\Foo;`
- Use `use` imports; avoid inline fully-qualified class names.
- Remove unused imports.

### Types
- Declare parameter and return types whenever practical.
- Prefer explicit types over `mixed`.
- Use nullable types only when null is valid behavior.
- Prefer value objects/DTOs over loose associative arrays for complex shapes.

### Naming
- Classes/interfaces/traits/enums: `PascalCase`
- Methods/functions: `camelCase`
- Variables/properties: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Test classes: `<Subject>Test`
- Test method names should describe behavior clearly.

### Error Handling
- Validate inputs and fail early.
- Throw specific exceptions for domain errors.
- Do not swallow exceptions silently.
- Add context when rethrowing.
- Keep error messages actionable.

### Comments and PHPDoc
- Prefer self-explanatory code over comments.
- Comment only non-obvious intent or edge-case rationale.
- Keep PHPDoc accurate.
- Avoid redundant docblocks for trivial fully-typed methods.

## Change Management for Agents
- Keep edits minimal and request-focused.
- Avoid introducing new frameworks or tools unless requested.
- Avoid broad refactors when fixing targeted issues.
- If adding tools, prefer wiring commands through Composer scripts.
- Run narrow checks first (single file/test), then broader checks if needed.
- Report exactly what was run and what could not be run.

## Cursor / Copilot Rules
Search status when this file was created:
- `.cursor/rules/`: not found
- `.cursorrules`: not found
- `.github/copilot-instructions.md`: not found

If these files are added later, treat them as high-priority instructions and update this document.

## Practical Agent Defaults
- Prefer safe, incremental edits over speculative architecture.
- Do not generate test/tooling scaffolds unless asked.
- Prefer Pest for test execution in this repository.
- If a command fails because tooling is absent, report that explicitly.
- Keep commit messages focused on intent and user-visible impact.
- Preserve existing public API behavior unless change request says otherwise.
- Avoid silent behavior changes; call out any unavoidable side effects.
- Favor deterministic code paths over magical conventions.
