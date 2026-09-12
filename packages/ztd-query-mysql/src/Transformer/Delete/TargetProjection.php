<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Delete;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Shadow\Mutation\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

/**
 * Target Projection.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class TargetProjection
{
    /**
     * @param array<string, array{alias: string}> $resolvedTables
     * @param array<string, array{viewSql: string}|array{columns: array<int, string>, primaryKeys?: array<int, string>}> $contexts
     * @return list<MultiTableMutationTarget>
     */
    public function targetsFromContexts(array $resolvedTables, array $contexts): array
    {
        $targets = [];
        foreach ($resolvedTables as $tableName => $tableInfo) {
            $context = $contexts[$tableName] ?? null;
            if (!isset($context['columns'])) {
                continue;
            }
            $targets[] = new MultiTableMutationTarget(
                $tableName,
                $context['columns'],
                $context['primaryKeys'] ?? [],
            );
        }

        return $targets;
    }

    /**
     * @param array<string, array{alias: string}> $resolvedTables
     * @param list<MultiTableMutationTarget> $targets
     */
    public function multiTableSelectList(array $resolvedTables, array $targets): string
    {
        $codec = new MultiTableMutationRow();
        $quoter = new MySqlIdentifierQuoter();
        $parts = [];
        foreach ($targets as $targetIndex => $target) {
            $tableInfo = $resolvedTables[$target->tableName()] ?? null;
            if ($tableInfo === null) {
                continue;
            }
            foreach ($target->matchColumns() as $columnIndex => $column) {
                $alias = $quoter->quote($tableInfo['alias']);
                $quotedColumn = $quoter->quote($column);
                $metadata = $quoter->quote($codec->valueColumn($targetIndex, $columnIndex));
                $parts[] = "$alias.$quotedColumn AS $metadata";
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Resolve Alias To Table for the supplied MySQL input.
     */
    public function resolveAliasToTable(string $alias, DeleteStatement $stmt): ?string
    {
        if ($stmt->from !== null && $stmt->from !== []) {
            foreach ($stmt->from as $from) {
                $fromAlias = \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::alias($from);
                if ($fromAlias === $alias) {
                    return \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::table($from);
                }
            }
        }

        if ($stmt->join !== null && $stmt->join !== []) {
            foreach ($stmt->join as $join) {
                if ($join->expr === null) {
                    continue;
                }
                $joinAlias = \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::alias($join->expr);
                if ($joinAlias === $alias) {
                    return \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::table($join->expr);
                }
            }
        }

        if ($stmt->using !== null && $stmt->using !== []) {
            foreach ($stmt->using as $using) {
                $usingAlias = \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::alias($using);
                if ($usingAlias === $alias) {
                    return \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::table($using);
                }
            }
        }

        return $alias;
    }
}
