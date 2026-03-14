import * as path from "node:path";
import * as vscode from "vscode";
import {
  LanguageClient,
  LanguageClientOptions,
  ServerOptions,
  Trace,
} from "vscode-languageclient/node";

let client: LanguageClient | undefined;

export async function activate(context: vscode.ExtensionContext): Promise<void> {
  const outputChannel = vscode.window.createOutputChannel("Sabre Language Server");
  context.subscriptions.push(outputChannel);

  client = await startClient(context, outputChannel);

  const restartCommand = vscode.commands.registerCommand(
    "sabre.restartLanguageServer",
    async () => {
      if (client) {
        await client.stop();
      }

      client = await startClient(context, outputChannel);
      vscode.window.showInformationMessage("Sabre language server restarted.");
    }
  );

  context.subscriptions.push(restartCommand);
}

export async function deactivate(): Promise<void> {
  if (!client) {
    return;
  }

  await client.stop();
  client = undefined;
}

async function startClient(
  context: vscode.ExtensionContext,
  outputChannel: vscode.OutputChannel
): Promise<LanguageClient> {
  const configuration = vscode.workspace.getConfiguration("sabre");

  const phpCommand = configuration.get<string>("phpCommand", "php");
  const configuredServerPath = configuration.get<string>(
    "serverPath",
    "bin/sabre-language-server"
  );

  const resolvedServerPath = resolveServerPath(context, configuredServerPath);

  const serverOptions: ServerOptions = {
    command: phpCommand,
    args: [resolvedServerPath],
    options: {
      cwd: getServerWorkingDirectory(),
      env: {
        ...process.env,
        SABRE_DEBUG: "1",
      },
    },
  };

  const clientOptions: LanguageClientOptions = {
    documentSelector: [
      { scheme: "file", language: "blade" },
      { scheme: "file", pattern: "**/*.blade.php" },
    ],
    outputChannel,
  };

  const languageClient = new LanguageClient(
    "sabreLanguageServer",
    "Sabre Blade Language Server",
    serverOptions,
    clientOptions
  );

  const traceLevel = configuration.get<string>("trace.server", "off");
  languageClient.setTrace(toTraceLevel(traceLevel));

  await languageClient.start();

  return languageClient;
}

function resolveServerPath(
  context: vscode.ExtensionContext,
  configuredPath: string
): string {
  if (path.isAbsolute(configuredPath)) {
    return configuredPath;
  }

  const workspaceFolder = vscode.workspace.workspaceFolders?.[0];
  if (workspaceFolder) {
    return path.resolve(workspaceFolder.uri.fsPath, configuredPath);
  }

  return path.resolve(context.extensionPath, "..", "..", configuredPath);
}

function getServerWorkingDirectory(): string {
  const workspaceFolder = vscode.workspace.workspaceFolders?.[0];

  if (workspaceFolder) {
    return workspaceFolder.uri.fsPath;
  }

  return process.cwd();
}

function toTraceLevel(trace: string): Trace {
  if (trace === "messages") {
    return Trace.Messages;
  }

  if (trace === "verbose") {
    return Trace.Verbose;
  }

  return Trace.Off;
}
