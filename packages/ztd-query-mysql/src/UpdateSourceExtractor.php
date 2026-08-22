<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

final class UpdateSourceExtractor
{
    public function extract(string $sql): ?string
    {
        $source = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(
            ['UPDATE'],
            [['SET']],
        );
        if ($source === null || $source === '') {
            return null;
        }

        foreach (SqlTokenStream::tokenize($source, MySqlLexerProfile::create())->significantTokens() as $token) {
            if ($token->isKeyword('LOW_PRIORITY') || $token->isKeyword('IGNORE')) {
                continue;
            }

            return substr($source, $token->offset);
        }

        return null;
    }
}
