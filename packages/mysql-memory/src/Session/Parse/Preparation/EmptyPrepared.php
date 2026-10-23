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
 * Semicolons and whitespace alone report an empty query; preceding comments form
 * a no-op query, which the prepared protocol cannot represent. Any other suffix
 * makes the leading semicolon a syntax error. Observed on MySQL 5.6.51, 5.7.44,
 * 8.0.44, 8.4.7 and 9.1.0. Tokens stay at the SQL input boundary.
 *
 * @visibility MySqlMemory
 */
final class EmptyPrepared
{
    /**
     * Refuses a terminated empty query before the ordinary statement splitter.
     *
     * @throws SqlError When a leading semicolon cannot begin a prepared statement
     */
    public function check(string $sql, SqlParser $parser, ?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset $client = null, bool $legacy = false): void
    {
        $first = (new InputTokens())->prefix($parser, $sql)[0] ?? null;
        if ($first?->text === ';') {
            $source = rtrim($sql, "; \t\n\r\f\v");
            if (strlen($source) > $first->offset) {
                throw (new \MySqlMemory\Session\Syntax($client))->near($first->offset, $source, '', new \SqlParser\Parser\SyntaxException($first, [], ''), $legacy, $parser);
            }
            throw (trim($first->leading) === '' ? StatementError::EmptyQuery : StatementError::UnsupportedPreparedStatement)->error();
        }
    }
}
