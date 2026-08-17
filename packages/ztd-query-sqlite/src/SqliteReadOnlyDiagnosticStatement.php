<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlTokenStream;

final class SqliteReadOnlyDiagnosticStatement
{
    public static function isSafe(string $sql): bool
    {
        $stream = SqlTokenStream::tokenize($sql);
        if (count($stream->splitStatements()) !== 1) {
            return false;
        }

        $tokens = $stream->significantTokens();
        if ($tokens === [] || !$tokens[0]->isKeyword('EXPLAIN') || count($tokens) === 1) {
            return false;
        }

        if ($tokens[1]->isKeyword('QUERY')) {
            return isset($tokens[2]) && $tokens[2]->isKeyword('PLAN') && isset($tokens[3]);
        }

        if ($tokens[1]->isKeyword('ANALYZE') || $tokens[1]->isKeyword('ANALYSE')) {
            return false;
        }

        return true;
    }
}
