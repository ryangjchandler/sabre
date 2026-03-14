# Sabre Test Workspace

Use this workspace to quickly validate the VSCode extension and language server behavior.

## How to use

1. Start the extension dev host from `editors/vscode` (`F5`).
2. In the Extension Development Host window, open this folder:
   - `editors/vscode/test-workspace`
3. Open the files in `resources/views/` and test hover/completion/diagnostics.

## Suggested manual checks

- `resources/views/completion.blade.php`
  - Place cursor after `@` and trigger completion.
- `resources/views/hover.blade.php`
  - Hover `@if` and `{{ $user->name }}`.
- `resources/views/diagnostics-invalid.blade.php`
  - Confirm a diagnostic appears for invalid Blade syntax.
- `resources/views/diagnostics-valid.blade.php`
  - Confirm there are no diagnostics.
- `resources/views/not-blade.php`
  - Confirm Blade diagnostics are ignored for non-Blade files.
- `resources/views/components-playground.blade.php`
  - Test component name, attribute, and slot completions (`<x-`, `<x-alert di`, `<x-alert-banner ic`, `<x-slot`, `<x-slot:ti`, `<x-slot name="fo`).

## Included sample components

- Anonymous components:
  - `resources/views/components/alert.blade.php`
  - `resources/views/components/form/input.blade.php`
  - `resources/views/components/modal.blade.php` (named slots: `title`, `footer`)
- Class component:
  - `app/View/Components/AlertBanner.php`
