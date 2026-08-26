<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The my sql read only diagnostic statement.
 */
final class MySqlReadOnlyDiagnosticStatement
{
    /** @var non-empty-list<string> */
    private const WRITE_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE',
        'LOAD', 'CALL', 'DO', 'EXECUTE',
        'GRANT', 'REVOKE',
    ];

    /**
     * Reports whether safe.
     *
     * @param string $sql
     * @return bool
     */
    public static function isSafe(string $sql): bool
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        if (count($stream->splitStatements()) !== 1) {
            return false;
        }

        $tokens = $stream->significantTokens();
        if ($tokens === []) {
            return false;
        }
        $first = $tokens[0];
        if ($first->isKeyword('SHOW') || $first->isKeyword('DESCRIBE') || $first->isKeyword('DESC')) {
            return true;
        }
        if (!$first->isKeyword('EXPLAIN') || count($tokens) === 1) {
            return false;
        }
        if (!self::containsKeyword($tokens, ['ANALYZE'])) {
            return true;
        }

        return !self::containsKeyword($tokens, self::WRITE_KEYWORDS);
    }

    /**
     * Reports whether any of these keywords is written among these tokens.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param non-empty-list<string> $keywords Keywords to look for
     *
     * @return bool True when one of them is written
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
