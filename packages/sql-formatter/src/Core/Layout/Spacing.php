<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Layout;

use SqlParser\Lexer\Token;

/**
 * Separates tokens without rewriting their contents or merging adjacent operators.
 *
 * @visibility SqlFormatter
 */
final class Spacing
{
    /**
     * Chooses an inline separator without changing lexical boundaries.
     */
    public static function between(?Token $previous, Token $token, bool $unary = false): string
    {
        if ($previous === null) {
            return '';
        }
        if ($token->text === '.' || $previous->text === '.') {
            return $token->leading === '' ? '' : ' ';
        }
        if (in_array($token->text, [',', ';', ')', ']', '::'], true)
            || in_array($previous->text, ['(', '[', '::'], true)) {
            return '';
        }
        if (in_array($token->text, ['(', '['], true)
            || in_array($previous->text, ['@', '@@', ':', '$'], true)) {
            return $token->leading === '' ? '' : ' ';
        }
        if ($unary && !in_array($token->text, ['+', '-', '~', '!'], true)) {
            return '';
        }
        return ' ';
    }
}
