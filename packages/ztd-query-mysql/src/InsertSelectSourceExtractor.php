<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

final class InsertSelectSourceExtractor
{
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
