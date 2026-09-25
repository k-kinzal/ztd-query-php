<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Syntax;

/**
 * Recognizes complete multiword clause headers in source order.
 *
 * @visibility SqlFormatter
 */
final class Headers
{
    /**
     * Shares the document being annotated.
     */
    public function __construct(private readonly Document $document, private readonly Rules $rules)
    {
    }

    /**
     * Marks the longest recognized header beginning at a token.
     */
    public function mark(int $start): void
    {
        $words = [];
        for ($index = $start; $index <= $start + 3; $index++) {
            $token = $this->document->tokens[$index] ?? null;
            if ($token === null) {
                break;
            }
            $words[] = strtoupper($token->text);
            if (in_array(implode(' ', $words), $this->rules->headers, true)) {
                $this->document->clauses[$start] = $index;
            }
        }
    }


}
