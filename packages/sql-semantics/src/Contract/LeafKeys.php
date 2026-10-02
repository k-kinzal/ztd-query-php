<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlParser\Lexer\Token;

/**
 * Maps the tokens of a parse tree to comparison keys for the token correspondence check.
 *
 * A keyword or punctuation token is keyed by its terminal, a name by its
 * decoded value, a literal by its exact decoded value. A token with no
 * influence on meaning in its production is noise and has no key; every such
 * position is listed with its reason in the database package.
 *
 * @visibility SqlSemantics
 */
interface LeafKeys
{
    /**
     * Answers the comparison key of a token, or null when the token is declared noise.
     *
     * @param Token $token The token
     * @param string $signature The production the token is a direct child of
     * @param int $position The position of the token among the children of that production
     */
    public function key(Token $token, string $signature, int $position): ?string;
}
