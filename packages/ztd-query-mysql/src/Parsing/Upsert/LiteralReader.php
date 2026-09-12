<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Upsert;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Literal Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */

final class LiteralReader
{
    /**
     * Identifier for the supplied MySQL input.
     */
    public function identifier(SqlToken $token): string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }
        $quote = $token->text[0] ?? '';
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }

    /**
     * Number for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function number(string $literal): int|float
    {
        if (preg_match('/^(?:0x[0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\z/', $literal) !== 1) {
            throw (new UnsupportedSqlException($literal, 'Unsupported UPSERT expression'));
        }
        if (str_starts_with($literal, '0x')) {
            return intval($literal, 16);
        }

        return strpbrk($literal, '.eE') === false ? (int) $literal : (float) $literal;
    }
}
