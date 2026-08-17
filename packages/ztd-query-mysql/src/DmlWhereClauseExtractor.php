<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenStream;

final class DmlWhereClauseExtractor
{
    public function extract(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, SqlTokenDialect::MySql)->topLevelClause(
            ['WHERE'],
            [['ORDER', 'BY'], ['LIMIT'], ['RETURNING']],
        );
    }
}
