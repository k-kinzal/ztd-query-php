<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenStream;

final class UpdateSourceExtractor
{
    public function extract(string $sql): ?string
    {
        $source = SqlTokenStream::tokenize($sql, SqlTokenDialect::MySql)->topLevelClause(
            ['UPDATE'],
            [['SET']],
        );
        if ($source === null || $source === '') {
            return null;
        }

        foreach (SqlTokenStream::tokenize($source, SqlTokenDialect::MySql)->significantTokens() as $token) {
            if ($token->isKeyword('LOW_PRIORITY') || $token->isKeyword('IGNORE')) {
                continue;
            }

            return trim(substr($source, $token->offset));
        }

        return null;
    }
}
