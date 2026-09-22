<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;

/**
 * Recognizes a single text literal, including PostgreSQL lexical string continuation.
 * @visibility SqlSemantics
 */
final class LiteralText
{
    /**
     * Uses lexical boundaries without decoding or evaluating the string value.
     */
    public static function accepts(string $text, Dialect $dialect): bool
    {
        try {
            $tokens = (new DialectParser($dialect))->parse('SELECT ' . $text)->tokens();
        } catch (\SqlParser\Lexer\LexicalException|\SqlParser\Parser\SyntaxException) {
            return false;
        }
        $tokens = array_values(array_filter($tokens, static fn ($token): bool => $token->text !== ''));
        if (count($tokens) !== 2) {
            return false;
        }
        $token = $tokens[1];
        return in_array($token->name, ['SCONST', 'USCONST', 'TEXT_STRING', 'NCHAR_STRING', 'STRING'], true)
            && $token->text === $text;
    }
}
