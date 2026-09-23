<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes table-maintenance requests from their typed targets and operation-specific options.
 * @visibility SqlSemantics
 */
final class MySqlMaintenance
{
    /**
     * Routes only the concrete storage-engine maintenance forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CheckTablesStatement,
            $statement instanceof Statement\RepairTablesStatement,
            $statement instanceof Statement\OptimizeTablesStatement,
            $statement instanceof Statement\AnalyzeTablesStatement,
            $statement instanceof Statement\ChecksumTablesStatement => self::tables($statement),
            $statement instanceof Statement\UpdateHistogramStatement,
            $statement instanceof Statement\ImportHistogramStatement,
            $statement instanceof Statement\DropHistogramStatement => Histograms::write($statement),
            default => null,
        };
    }

    /**
     * Emits only the flags permitted by the operation's native operand types.
     */
    public static function tables(Statement\CheckTablesStatement|Statement\RepairTablesStatement|Statement\OptimizeTablesStatement|Statement\AnalyzeTablesStatement|Statement\ChecksumTablesStatement $statement): Tree
    {
        $binlog = $statement instanceof Statement\RepairTablesStatement || $statement instanceof Statement\OptimizeTablesStatement || $statement instanceof Statement\AnalyzeTablesStatement ? $statement->binlog->value : '';
        $options = match (true) {
            $statement instanceof Statement\CheckTablesStatement,
            $statement instanceof Statement\RepairTablesStatement => array_map(static fn ($option): Tree => Build::keyword($option->value), $statement->options),
            $statement instanceof Statement\ChecksumTablesStatement => [Build::keyword($statement->mode->value)],
            $statement instanceof Statement\OptimizeTablesStatement,
            $statement instanceof Statement\AnalyzeTablesStatement => [],
        };
        return new Tree('table_maintenance', [Build::keyword($statement->kind->value), Build::keyword($binlog), Build::keyword('TABLE'), Build::separated(array_map(static fn ($table): Tree => Relations::target($table, $statement->origin->dialect), $statement->tables)), ...$options]);
    }
}
