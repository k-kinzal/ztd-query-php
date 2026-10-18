<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Preparation;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Platform\MySql\Lowering\InputTokens;

/**
 * Classifies an empty first query terminated before a prepared statement begins.
 *
 * A leading semicolon reports an empty query; preceding comments instead form
 * a no-op query, which the prepared protocol cannot represent. Later statements
 * do not change this first-query outcome. Observed on MySQL 5.6.51, 5.7.44,
 * 8.0.44, 8.4.7 and 9.1.0. Tokens stay at the SQL input boundary.
 *
 * @visibility MySqlMemory
 */
final class EmptyPrepared
{
    /**
     * Refuses a terminated empty query before the ordinary statement splitter.
     *
     * @throws SqlError When the first query contains only whitespace or comments
     */
    public function check(string $sql, SqlParser $parser): void
    {
        $first = (new InputTokens())->prefix($parser, $sql)[0] ?? null;
        if ($first?->text === ';') {
            throw (trim($first->leading) === '' ? StatementError::EmptyQuery : StatementError::UnsupportedPreparedStatement)->error();
        }
    }
}
