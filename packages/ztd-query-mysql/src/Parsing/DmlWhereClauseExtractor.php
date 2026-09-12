<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the Dml Where Clause Extractor contract for MySQL.
 */
final class DmlWhereClauseExtractor
{
    /**
     * Extract for the supplied MySQL input.
     */
    public function extract(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(
            ['WHERE'],
            [['ORDER', 'BY'], ['LIMIT'], ['RETURNING']],
        );
    }
}
