<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Rewrite;

use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql read only diagnostic statement.
 */
final class PgSqlReadOnlyDiagnosticStatement
{
    /** @var non-empty-list<string> */
    private const WRITE_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'MERGE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE',
        'COPY', 'CALL', 'DO', 'EXECUTE',
        'GRANT', 'REVOKE', 'VACUUM',
    ];

    /**
     * Reports whether safe.
     *
     * @param string $sql
     * @return bool
     */
    public static function isSafe(string $sql): bool
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        if (count($stream->splitStatements()) !== 1) {
            return false;
        }

        $tokens = $stream->significantTokens();
        if ($tokens === []) {
            return false;
        }
        if ($tokens[0]->isKeyword('SHOW')) {
            return count($tokens) > 1;
        }
        if (!$tokens[0]->isKeyword('EXPLAIN') || count($tokens) === 1) {
            return false;
        }
        if (!self::containsKeyword($tokens, ['ANALYZE', 'ANALYSE'])) {
            return true;
        }

        return !self::containsKeyword($tokens, self::WRITE_KEYWORDS);
    }

    /**
     * Reports whether any of these keywords is written among these tokens.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param non-empty-list<string> $keywords Keywords to look for, in order
     *
     * @return bool What it answers
     */
    public static function containsKeyword(array $tokens, array $keywords): bool
    {
        foreach ($tokens as $token) {
            foreach ($keywords as $keyword) {
                if ($token->isKeyword($keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
