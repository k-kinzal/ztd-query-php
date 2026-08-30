<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parse;

use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The update source extractor.
 */
final class UpdateSourceExtractor
{
    /**
     * Reads.
     *
     * @param string $sql
     * @return ?string
     */
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
