<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing;

use ZtdQuery\Sql\SqlToken;

/**
 * Diagnostic Keywords.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class DiagnosticKeywords
{
    /**
     * @param list<SqlToken> $tokens
     * @param non-empty-list<string> $keywords
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
