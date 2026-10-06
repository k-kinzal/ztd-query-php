<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlFixture\Syntax\NumericLiteral;
use SqlFixture\Syntax\SqlText;
use SqlParser\Parser\Node;

/**
 * Interprets the DEFAULT attribute of a column, keeping expressions as SQL text.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Returns the literal value a DEFAULT attribute declares, or the text of its expression.
     */
    public function extractDefault(Node $attribute): int|float|bool|string|null
    {
        $literal = (new NodeReader())->child($attribute, 'now_or_signed_literal');
        if ($literal === null) {
            return (new SqlText())->ofTokens(array_slice($attribute->tokens(), 1));
        }
        $sign = '';
        $strings = $this->strings($literal);
        if ($strings !== null) {
            return $strings;
        }
        foreach ($literal->tokens() as $token) {
            if ($token->text === '-' || $token->text === '+') {
                $sign = $token->text;
                continue;
            }
            if ($token->is('NUM') || $token->is('LONG_NUM') || $token->is('ULONGLONG_NUM') || $token->is('DECIMAL_NUM') || $token->is('FLOAT_NUM')) {
                return (new NumericLiteral())->decode($sign . $token->text);
            }
            if ($token->is('TRUE_SYM')) {
                return true;
            }
            if ($token->is('FALSE_SYM')) {
                return false;
            }
            if ($token->is('NULL_SYM')) {
                return null;
            }
            if (!$token->is('UNDERSCORE_CHARSET')) {
                break;
            }
        }

        return (new SqlText())->ofNode($literal);
    }

    /**
     * Answers the text a string literal spells, strings written next to one another joined, or null for another literal.
     *
     * A date or a byte string is written as a literal of its own kind rather
     * than as the text one, and is left to be read as the SQL it was written
     * as.
     */
    public function strings(Node $literal): ?string
    {
        $written = $literal->find('text_literal')[0] ?? null;
        if ($written === null) {
            return null;
        }
        $text = '';
        foreach ($written->tokens() as $token) {
            if ($token->is('TEXT_STRING') || $token->is('NCHAR_STRING')) {
                $text .= (new StringLiteral())->decode($token);
            }
        }

        return $text;
    }
}
