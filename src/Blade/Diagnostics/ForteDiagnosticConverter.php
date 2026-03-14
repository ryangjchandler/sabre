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

        $code = $diagnostic->code ?? 'unknown';
        $suggestions = $this->suggestionsFor($diagnostic->source, $code);
        $message = $this->formatMessage($diagnostic, $suggestions);

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
            'message' => $message,
            'code' => $code,
            'data' => [
                'origin' => 'forte',
                'source' => $diagnostic->source,
                'code' => $code,
                'rawMessage' => $diagnostic->message,
                'suggestions' => $suggestions,
            ],
        ];
    }

    /**
     * @param  list<string>  $suggestions
     */
    private function formatMessage(Diagnostic $diagnostic, array $suggestions): string
    {
        $base = sprintf(
            '[%s:%s] %s',
            strtoupper($diagnostic->source),
            $diagnostic->code ?? 'unknown',
            $diagnostic->message
        );

        if ($suggestions === []) {
            return $base;
        }

        return $base."\n".'Try: '.implode(' ', array_map(
            static fn (string $hint): string => sprintf('- %s', $hint),
            $suggestions
        ));
    }

    /**
     * @return list<string>
     */
    private function suggestionsFor(string $source, string $code): array
    {
        $normalizedSource = strtolower($source);
        $normalizedCode = strtolower($code);

        if ($normalizedSource === 'lexer' && str_contains($normalizedCode, 'unexpectedeof')) {
            return [
                'close any unclosed Blade echo like {{ ... }}',
                'close directives such as @if with @endif',
                'check for an unfinished HTML tag near the reported range',
            ];
        }

        if ($normalizedSource === 'parser' && str_contains($normalizedCode, 'unexpectedtoken')) {
            return [
                'check for misplaced punctuation or unmatched parentheses',
                'ensure Blade directives and component tags are balanced',
            ];
        }

        return [
            'review syntax around the highlighted range',
            'check for missing closing tags, directives, or delimiters',
        ];
    }
}
