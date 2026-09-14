<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;

/**
 * Separates fixture column metadata from physical table partitioning.
 *
 * @visibility root
 */
final class TableDefinitionInput
{
    /**
     * Keeps the complete column list and table options before PARTITION BY.
     *
     * Partition definitions do not affect generated row values. Removing this
     * suffix also prevents sql-parser from interpreting a following AS VALUES
     * expression as a partition definition and reading beyond its token list.
     */
    public function withoutPartitioning(string $sql): string
    {
        $depth = 0;
        $hasDefinition = false;
        foreach ((new Lexer($sql))->list->tokens as $token) {
            if ($token->type === Token::TYPE_OPERATOR) {
                if ($token->value === '(') {
                    $depth++;
                } elseif ($token->value === ')') {
                    $depth--;
                    $hasDefinition = $depth === 0;
                }
            }
            if ($hasDefinition && $depth === 0 && $token->type === Token::TYPE_KEYWORD && $token->keyword === 'PARTITION BY') {
                return rtrim(mb_substr($sql, 0, $token->position, 'UTF-8'));
            }
        }
        return $sql;
    }
}
