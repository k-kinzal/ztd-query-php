<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\SqlPlaceholderEscaper;

/**
 * Pdo placeholder escaper for PostgreSQL queries.
 */
final class PgSqlPdoPlaceholderEscaper implements SqlPlaceholderEscaper
{
    /**
     * Escapes operator question marks while preserving PDO placeholders and quoted SQL.
     */
    public function escape(string $sql): string
    {
        $cursor = new Driver\Placeholder\EscapeCursor($sql);
        $quoted = new Driver\Placeholder\QuotedInput();
        $operand = new Driver\Placeholder\OperandInput();
        while ($cursor->position < strlen($sql)) {
            if ($quoted->consumeIgnored($cursor) || $quoted->consumeQuoted($cursor)
                || $operand->consumeOperator($cursor) || $operand->consumeOperand($cursor)
            ) {
                continue;
            }
            $char = $sql[$cursor->position];
            $cursor->preserve(1);
            $cursor->expectsOperand = match ($char) {
                ')', ']' => false,
                '(', '[', ',', ';', '.', '=', '<', '>', '!', '~', '+', '-', '*', '/', '%', '^', '|', '&', '#', '@' => true,
                default => $cursor->expectsOperand,
            };
        }
        return $cursor->result;
    }
}
