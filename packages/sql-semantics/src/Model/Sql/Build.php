<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlSemantics\Dialect;

/**
 * Constructs SQL components with explicit identifier and literal boundaries.
 *
 * @visibility SqlSemantics
 */
final class Build
{
    /**
     * @param list<Tree> $items
     */
    public static function separated(array $items, string $separator = ','): Tree
    {
        $parts = [];
        foreach ($items as $item) {
            if ($parts !== []) {
                $parts[] = new Atom('punctuation', $separator);
            }
            $parts[] = $item;
        }
        return new Tree('list', $parts);
    }

    /**
     * Protects the precedence of a replacement expression.
     */
    public static function parentheses(Tree $value): Tree
    {
        return new Tree('parentheses', [new Atom('punctuation', '('), $value, new Atom('punctuation', ')')]);
    }

    /**
     * Quotes names as identifiers, including embedded quote characters.
     *
     * @param non-empty-list<string> $parts
     */
    public static function identifier(array $parts, Dialect $dialect): Tree
    {
        $quote = $dialect === Dialect::MySql ? '`' : '"';
        return self::separated(array_map(static fn (string $part): Tree => new Tree('identifier', [new Atom('identifier', $quote . str_replace($quote, $quote . $quote, $part) . $quote)]), $parts), '.');
    }

    /**
     * Declares SQL keywords and punctuation, never user-supplied values.
     */
    public static function keyword(string $text): Tree
    {
        return new Tree('keyword', [new Atom('keyword', $text)]);
    }
}
