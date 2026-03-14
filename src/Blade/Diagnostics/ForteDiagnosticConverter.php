<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Blade\Diagnostics;

use Forte\Ast\Document\Document;
use Forte\Diagnostics\Diagnostic;

final class ForteDiagnosticConverter
{
    /**
     * @return array<string, mixed>
     */
    public function toLsp(Document $document, Diagnostic $diagnostic): array
    {
        $startOffset = max(0, $diagnostic->start);
        $endOffset = max($startOffset, $diagnostic->end);

        $start = $document->getLineAndColumnForOffset($startOffset);
        $end = $document->getLineAndColumnForOffset($endOffset);

        return [
            'range' => [
                'start' => [
                    'line' => $start['line'] - 1,
                    'character' => $start['column'] - 1,
                ],
                'end' => [
                    'line' => $end['line'] - 1,
                    'character' => $end['column'] - 1,
                ],
            ],
            'severity' => $diagnostic->severity->value,
            'source' => sprintf('sabre.%s', $diagnostic->source),
            'message' => $diagnostic->message,
            'code' => $diagnostic->code,
        ];
    }
}
