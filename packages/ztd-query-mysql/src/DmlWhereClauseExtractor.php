<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * The dml where clause extractor.
 */
final class DmlWhereClauseExtractor
{
    /**
     * Reads.
     *
     * @param string $sql
     * @return ?string
     */
    public function extract(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(
            ['WHERE'],
            [['ORDER', 'BY'], ['LIMIT'], ['RETURNING']],
        );
    }
}
