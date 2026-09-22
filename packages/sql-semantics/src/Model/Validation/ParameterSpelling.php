<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;

/**
 * Validates one parameter binding key with the database's lexical rules.
 * @visibility SqlSemantics
 */
final class ParameterSpelling
{
    /**
     * Rejects SQL fragments and trivia masquerading as one binding key.
     */
    public static function accepts(string $name, Dialect $dialect): bool
    {
        try {
            $tokens = (new DialectParser($dialect))->parse('SELECT ' . $name)->tokens();
        } catch (\SqlParser\Lexer\LexicalException|\SqlParser\Parser\SyntaxException) {
            return false;
        }
        $tokens = array_values(array_filter($tokens, static fn ($token): bool => $token->text !== ''));
        return count($tokens) === 2 && in_array($tokens[1]->name, ['PARAM', 'PARAM_MARKER', 'VARIABLE'], true) && $tokens[1]->text === $name;
    }
}
