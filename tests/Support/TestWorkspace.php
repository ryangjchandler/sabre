<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class TestWorkspace
{
    private function __construct(private readonly string $rootPath)
    {
    }

    public static function create(): self
    {
        $path = sprintf('%s/sabre-tests-%s', rtrim(sys_get_temp_dir(), '/'), bin2hex(random_bytes(8)));

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create test workspace at "%s".', $path));
        }

        return new self($path);
    }

    public function __destruct()
    {
        if (!is_dir($this->rootPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->rootPath);
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function rootUri(): string
    {
        return $this->pathToUri($this->rootPath);
    }

    public function put(string $relativePath, string $content, string $cursorToken = '[[cursor]]'): TestDocument
    {
        $normalizedPath = ltrim($relativePath, '/');
        [$contentWithoutCursor, $line, $character] = $this->extractCursorPosition($content, $cursorToken);

        $absolutePath = sprintf('%s/%s', $this->rootPath, $normalizedPath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create test directory "%s".', $directory));
        }

        if (file_put_contents($absolutePath, $contentWithoutCursor) === false) {
            throw new RuntimeException(sprintf('Unable to write test document "%s".', $absolutePath));
        }

        return new TestDocument(
            $normalizedPath,
            $absolutePath,
            $this->pathToUri($absolutePath),
            $contentWithoutCursor,
            $line,
            $character
        );
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function extractCursorPosition(string $content, string $cursorToken): array
    {
        $offset = strpos($content, $cursorToken);

        if ($offset === false) {
            return [$content, null, null];
        }

        $secondOffset = strpos($content, $cursorToken, $offset + strlen($cursorToken));
        if ($secondOffset !== false) {
            throw new RuntimeException('Expected at most one cursor token in document content.');
        }

        $contentWithoutCursor = substr_replace($content, '', $offset, strlen($cursorToken));
        $beforeCursor = substr($contentWithoutCursor, 0, $offset);

        $line = substr_count($beforeCursor, "\n");
        $lastNewLineOffset = strrpos($beforeCursor, "\n");
        $character = $lastNewLineOffset === false
            ? strlen($beforeCursor)
            : strlen(substr($beforeCursor, $lastNewLineOffset + 1));

        return [$contentWithoutCursor, $line, $character];
    }

    private function pathToUri(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        return sprintf('file://%s', $normalized);
    }
}
