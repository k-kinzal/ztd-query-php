<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

/**
 * Distinguishes boolean conjunctions, range predicates, signs, and CASE expressions.
 *
 * @visibility SqlFormatter
 */
final class Expressions
{
    /**
     * Shares the document being annotated.
     */
    public function __construct(private readonly Document $document)
    {
    }

    /**
     * @param array<int, string> $direct
     */
    public function mark(string $name, int $start, int $end, array $direct): void
    {
        if (!in_array($name, ['expr', 'a_expr', 'b_expr', 'simple_expr', 'case_expr', 'signed_literal', 'ccons'], true)) {
            return;
        }
        $between = in_array('BETWEEN', array_map(strtoupper(...), $direct), true);
        foreach ($direct as $index => $text) {
            $word = strtoupper($text);
            if (in_array($word, ['AND', 'OR', 'XOR'], true) && !$between) {
                $this->document->logical[$index] = true;
            }
            if ($index === $start && in_array($text, ['+', '-', '~', '!'], true)) {
                $this->document->unary[$index] = true;
            }
            if ($word === 'CASE') {
                $this->document->casePairs[$index] = $end;
            }
        }
    }
}
