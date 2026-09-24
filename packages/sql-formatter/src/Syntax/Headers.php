<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

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
    public function __construct(private readonly Document $document)
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
            if (in_array(implode(' ', $words), Rules::HEADERS, true)) {
                $this->document->clauses[$start] = $index;
            }
        }
    }


}
