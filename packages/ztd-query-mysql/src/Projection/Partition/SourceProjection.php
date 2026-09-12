<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Partition;

use ZtdQuery\Sql\SqlToken;

/**
 * Projects a partition predicate into a derived relation while preserving its alias.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class SourceProjection
{
    /**
     * @param array{name: string, start: int, unqualifiedStart: int, end: int} $reference
     * @param list<SqlToken> $tokens
     * @return array{start: int, end: int, replacement: string}
     */
    public function edit(string $sql, array $reference, array $tokens, int $closeIndex, string $predicate): array
    {
        $tableSql = substr(
            $sql,
            $reference['unqualifiedStart'],
            $reference['end'] - $reference['unqualifiedStart'],
        );
        $replacement = "(SELECT * FROM $tableSql WHERE $predicate)";
        if (!(new SelectionReader())->hasAlias($tokens, $closeIndex + 1)) {
            $replacement .= " AS $tableSql";
        }
        return [
            'start' => $reference['start'],
            'end' => $tokens[$closeIndex]->endOffset(),
            'replacement' => $replacement,
        ];
    }
}
