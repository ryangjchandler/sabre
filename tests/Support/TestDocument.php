<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Tests\Support;

final class TestDocument
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $path,
        public readonly string $uri,
        public readonly string $content,
        public readonly ?int $cursorLine,
        public readonly ?int $cursorCharacter
    ) {
    }

    public function hasCursor(): bool
    {
        return $this->cursorLine !== null && $this->cursorCharacter !== null;
    }
}
