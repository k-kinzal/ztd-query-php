<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlFixture\Syntax\NumericLiteral;
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
    public function extractDefault(Node $attribute, string $sql): int|float|bool|string|null
    {
        $reader = new NodeReader();
        $literal = $reader->child($attribute, 'now_or_signed_literal');
        if ($literal === null) {
            return $reader->textOf(array_slice($attribute->tokens(), 1), $sql);
        }
        $sign = '';
        foreach ($literal->tokens() as $token) {
            if ($token->text === '-' || $token->text === '+') {
                $sign = $token->text;
                continue;
            }
            if ($token->is('TEXT_STRING') || $token->is('NCHAR_STRING')) {
                return (new StringLiteral())->decode($token);
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

        return $literal->text($sql);
    }
}
