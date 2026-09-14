<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Sql\Diagnostic;

use ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Read Only Diagnostic Statement contract for MySQL.
 */
final class MySqlReadOnlyDiagnosticStatement
{
    /**
     * @var non-empty-list<string>
     */
    private const WRITE_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE',
        'LOAD', 'CALL', 'DO', 'EXECUTE',
        'GRANT', 'REVOKE',
    ];

    /**
     * Is Safe for the supplied MySQL input.
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
        if (!DiagnosticKeywords::containsKeyword($tokens, ['ANALYZE'])) {
            return true;
        }

        return !DiagnosticKeywords::containsKeyword($tokens, self::WRITE_KEYWORDS);
    }

}
