<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Blade;

use Forte\Ast\Document\Document;
use Forte\Parser\ParserOptions;
use RuntimeException;

final class ForteDocumentParser
{
    public function __construct(private readonly ?ParserOptions $parserOptions = null) {}

    public function parse(string $template, ?string $filePath = null): Document
    {
        $document = Document::parse($template, $this->parserOptions);

        if ($filePath !== null) {
            $document->setFilePath($filePath);
        }

        return $document;
    }

    public function parseFile(string $path): Document
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Template file does not exist: %s', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read template file: %s', $path));
        }

        return $this->parse($contents, $path);
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, Document>
     */
    public function parseFiles(array $paths): array
    {
        $documents = [];

        foreach ($paths as $path) {
            $documents[$path] = $this->parseFile($path);
        }

        return $documents;
    }

    public function parseUri(string $uri): Document
    {
        return $this->parseFile($this->uriToPath($uri));
    }

    public function uriToPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(sprintf('Unable to resolve file path from URI: %s', $uri));
        }

        return rawurldecode($path);
    }
}
