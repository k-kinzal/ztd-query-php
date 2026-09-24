<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Compares grammar derivations and exact token spellings independently of whitespace.
 *
 * @visibility SqlFormatter
 */
final class Fingerprint
{
    /**
     * Builds a stable signature of token spellings and grammar alternatives.
     */
    public static function of(Node|Token $node): string
    {
        if ($node instanceof Token) {
            return serialize([$node->name, $node->text]);
        }
        return hash('sha256', serialize([$node->name, $node->ordinal, array_map(self::of(...), $node->children)]));
    }
}
