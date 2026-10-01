<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Lexer\Token;
use SqlSemantics\Statement\Literal\Literal;

/**
 * Decodes the literal spellings of a language without evaluating expressions.
 * @visibility SqlSemantics
 */
interface LiteralRules
{
    /**
     * @param non-empty-list<Token> $tokens
     * @throws \SqlSemantics\Core\Literal\DecodingException When the tokens are not one supported literal
     */
    public function decode(array $tokens): Literal;
}
