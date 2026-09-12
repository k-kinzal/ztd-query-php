<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\Classification;

use PhpMyAdmin\SqlParser\Statement;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Cte Statement Kind.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class CteStatementKind
{
    /**
     * Classify With Fallback for the supplied MySQL input.
     */
    public function classifyWithFallback(string $sql): ?QueryKind
    {
        $seenBody = false;
        $kinds = [
            'SELECT' => QueryKind::READ,
            'UPDATE' => QueryKind::WRITE_SIMULATED,
            'DELETE' => QueryKind::WRITE_SIMULATED,
            'INSERT' => QueryKind::WRITE_SIMULATED,
            'REPLACE' => QueryKind::WRITE_SIMULATED,
            'TRUNCATE' => QueryKind::WRITE_SIMULATED,
            'CREATE' => QueryKind::DDL_SIMULATED,
            'DROP' => QueryKind::DDL_SIMULATED,
            'ALTER' => QueryKind::DDL_SIMULATED,
        ];
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                $seenBody = true;
                continue;
            }
            if (!$seenBody || !$token->isTopLevel() || $token->kind !== \ZtdQuery\Sql\SqlTokenKind::Word) {
                continue;
            }
            $kind = $kinds[strtoupper($token->text)] ?? null;
            if ($kind !== null) {
                return $kind;
            }
        }
        return null;
    }
}
