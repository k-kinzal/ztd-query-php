<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the Insert Select Source Extractor contract for MySQL.
 */
final class InsertSelectSourceExtractor
{
    /**
     * Extract for the supplied MySQL input.
     */
    public function extract(string $sql): ?string
    {
        $selectBody = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(
            ['SELECT'],
            [['ON', 'DUPLICATE', 'KEY', 'UPDATE'], ['RETURNING']],
        );
        if ($selectBody === null || $selectBody === '') {
            return null;
        }

        return 'SELECT ' . $selectBody;
    }
}
