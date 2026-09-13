<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Removes physical index hints from relations supplied by shadow CTEs.
 */
final class SqliteIndexHintStripper
{
    /**
     * @param list<string> $shadowTables
     */
    public function strip(string $sql, array $shadowTables): string
    {
        $targets = array_map(
            static fn (string $table): string => strtolower($table),
            $shadowTables,
        );

        $stream = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create());
        $tokens = $stream->significantTokens();
        /**
         * @var list<array{start: int, end: int}> $removals
         */
        $removals = [];

        foreach ((new SqliteSelectRelationParser())->references($sql) as $reference) {
            if (!in_array(strtolower($reference['name']), $targets, true)) {
                continue;
            }
            $index = Rewriting\Index\IndexHintTokens::tokenIndexAtOrAfter($tokens, $reference['end']);
            $index = Rewriting\Index\IndexHintTokens::skipAlias($tokens, $index);
            $range = Rewriting\Index\IndexHintTokens::hintRange($tokens, $index);
            if ($range === null) {
                continue;
            }
            $removals[] = $range;
        }

        usort($removals, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($removals as $removal) {
            $sql = substr_replace($sql, '', $removal['start'], $removal['end'] - $removal['start']);
        }

        return $sql;
    }

}
