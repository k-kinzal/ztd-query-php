<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Walks the CST once; the grammar distinguishes list commas and boolean operators.
 *
 * @visibility SqlFormatter
 */
final class Analyzer
{
    /**
     * Shares the token sequence and layout annotations under construction.
     */
    public function __construct(private readonly Document $document)
    {
    }

    /**
     * @return array{int, int}
     */
    public function visit(Node $node): array
    {
        $start = count($this->document->tokens);
        $direct = [];
        foreach ($node->children as $child) {
            $index = count($this->document->tokens);
            if ($child instanceof Token) {
                if ($child->text !== '' || $child->leading !== '') {
                    $this->document->tokens[] = $child;
                    $direct[$index] = $child->text;
                }
            } else {
                $this->visit($child);
                if (in_array($child->name, ['join_type', 'insert_cmd', 'between_op'], true) && isset($this->document->tokens[$index])) {
                    $direct[$index] = $this->document->tokens[$index]->text;
                }
                if (in_array($child->name, ['and', 'or', 'xor'], true)) {
                    $this->document->logical[$index] = true;
                }
            }
        }
        $end = count($this->document->tokens) - 1;
        if ($end >= $start) {
            (new Markers($this->document))->apply($node->name, $start, $end, $direct);
        }
        return [$start, $end];
    }

}
