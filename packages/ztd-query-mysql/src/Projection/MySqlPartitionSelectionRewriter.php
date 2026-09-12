<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\TablePartitioning;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Rewrites explicit MySQL partition selection into filtered table sources.
 */
final class MySqlPartitionSelectionRewriter
{
    /**
     * @param array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>,
     *     partitioning?: TablePartitioning|null
     * }> $tables
     * @throws UnsupportedSqlException
     */
    public function rewrite(string $sql, array $tables): string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $contexts = [];
        foreach ($tables as $table => $context) {
            $contexts[strtolower($table)] = $context;
        }

        $edits = [];
        foreach ((new MySqlSelectRelationParser())->references($sql) as $reference) {
            $partitionIndex = (new Projection\Partition\SelectionReader())->tokenIndexAtOrAfter($tokens, $reference['end']);
            $partition = $tokens[$partitionIndex] ?? null;
            if (!$partition instanceof SqlToken || !$partition->isKeyword('PARTITION')) {
                continue;
            }

            $selection = (new Projection\Partition\SelectionReader())->partitionSelection($sql, $tokens, $partitionIndex);
            $names = $selection['names'];
            $closeIndex = $selection['closeIndex'];
            $context = $contexts[strtolower($reference['name'])] ?? null;
            $partitioning = $context['partitioning'] ?? null;
            $predicates = $partitioning instanceof TablePartitioning
                ? $partitioning->predicatesFor($names)
                : null;
            if ($predicates === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            $predicate = implode(' OR ', array_map(
                static fn (string $partitionPredicate): string => "($partitionPredicate)",
                $predicates,
            ));

            (new Projection\Partition\SelectionReader())->requireNoIndexHint($sql, $tokens, $closeIndex);
            $edits[] = (new Projection\Partition\SourceProjection())->edit($sql, $reference, $tokens, $closeIndex, $predicate);
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

}
