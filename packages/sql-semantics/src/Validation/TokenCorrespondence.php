<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\LeafKeys;
use SqlSemantics\Lowering\Productions;

/**
 * Compares the significant tokens of the analyzed SQL with those of the rendered SQL.
 *
 * Both parse trees are reduced to the ordered comparison keys of their tokens.
 * Equal sequences establish that rendering the model reproduces every
 * significant token of the input in order; whitespace, comments, letter case
 * of keywords, equivalent spellings of one name or literal, and the positions
 * a database package declares as noise are not significant.
 *
 * @visibility SqlSemantics
 */
final class TokenCorrespondence
{
    /**
     * Answers the ordered comparison keys of the tokens of a parse tree.
     *
     * @return list<string>
     */
    public function keys(Node $tree, Productions $productions, LeafKeys $rules): array
    {
        $keys = [];
        $frames = [[$tree, $productions->signature($tree), 0]];
        while ($frames !== []) {
            $top = count($frames) - 1;
            [$node, $signature, $position] = $frames[$top];
            if ($position >= count($node->children)) {
                array_pop($frames);
                continue;
            }
            $frames[$top][2] = $position + 1;
            $child = $node->children[$position];
            if ($child instanceof Token) {
                $key = $rules->key($child, $signature, $position);
                if ($key !== null) {
                    $keys[] = $key;
                }
            } else {
                $frames[] = [$child, $productions->signature($child), 0];
            }
        }

        return $keys;
    }

    /**
     * Answers a description of the first difference between two key sequences, or null when they are equal.
     *
     * @param list<string> $source
     * @param list<string> $rendered
     */
    public function difference(array $source, array $rendered): ?string
    {
        $length = max(count($source), count($rendered));
        for ($index = 0; $index < $length; $index++) {
            $expected = $source[$index] ?? '(end)';
            $actual = $rendered[$index] ?? '(end)';
            if ($expected !== $actual) {
                return 'token ' . $index . ': source has ' . $expected . ', rendered SQL has ' . $actual;
            }
        }

        return null;
    }
}
