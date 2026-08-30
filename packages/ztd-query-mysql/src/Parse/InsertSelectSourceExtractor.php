<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parse;

use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The insert select source extractor.
 */
final class InsertSelectSourceExtractor
{
    /**
     * Reads.
     *
     * @param string $sql
     * @return ?string
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
