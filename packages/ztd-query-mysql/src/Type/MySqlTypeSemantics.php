<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Restores native operators that cannot be represented by a CTE column cast.
 */
final class MySqlTypeSemantics
{
    /**
     * @param array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        [$qualified, $unqualified] = (new Type\Enum\RankEdits())->enumColumns($tables);
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        $edits = (new Type\Enum\RankEdits())->comparisonEdits($sql, $tokens, $qualified, $unqualified);
        foreach ((new Type\Enum\RankEdits())->orderByEdits($sql, $tokens, $qualified, $unqualified) as $key => $edit) {
            $edits[$key] = $edit;
        }

        uasort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr($sql, 0, $edit['start']) . $edit['replacement'] . substr($sql, $edit['end']);
        }

        return $sql;
    }

}
