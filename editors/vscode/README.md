# Sabre VSCode Extension

This extension starts the Sabre language server for Blade files.

## Development

1. Install dependencies:

   ```bash
   npm install
   ```

2. Build the extension:

   ```bash
   npm run compile
   ```

3. Open this folder in VSCode:

   ```text
   editors/vscode
   ```

4. Press `F5` to launch the Extension Development Host.

5. In the new window, open a Laravel project containing `.blade.php` files.

For quick local testing, open:

```text
editors/vscode/test-workspace
```

## Settings

- `sabre.phpCommand` (default: `php`)
  - PHP binary used to launch the language server.
- `sabre.serverPath` (default: `bin/sabre-language-server`)
  - Path to the Sabre language server script.
  - Relative paths are resolved from the first workspace folder.
- `sabre.trace.server` (`off` | `messages` | `verbose`)
  - LSP trace verbosity.

## Command

- `Sabre: Restart Language Server`
  - Restarts the running language server process.
