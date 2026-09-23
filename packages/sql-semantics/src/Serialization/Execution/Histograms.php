<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes the distinct sampling, import and removal forms from their semantic operands.
 * @visibility SqlSemantics
 */
final class Histograms
{
    /**
     * Keeps imported data separate from sampling settings and removal requests.
     */
    public static function write(Statement\UpdateHistogramStatement|Statement\ImportHistogramStatement|Statement\DropHistogramStatement $statement): Tree
    {
        $columns = $statement instanceof Statement\ImportHistogramStatement ? [$statement->column] : $statement->columns;
        $action = $statement instanceof Statement\DropHistogramStatement ? 'DROP HISTOGRAM ON' : 'UPDATE HISTOGRAM ON';
        $options = match (true) {
            $statement instanceof Statement\DropHistogramStatement => [],
            $statement instanceof Statement\ImportHistogramStatement => [Build::keyword('USING DATA'), Expressions::write($statement->data)],
            $statement instanceof Statement\UpdateHistogramStatement => [...($statement->buckets === null ? [] : [Build::keyword('WITH'), new Tree('bucket_count', [new Atom('number', (string) $statement->buckets->value)]), Build::keyword('BUCKETS')]), Build::keyword($statement->refresh->value)],
        };
        return new Tree('histogram_maintenance', [Build::keyword('ANALYZE'), Build::keyword($statement->binlog->value), Build::keyword('TABLE'), Relations::target($statement->table, $statement->origin->dialect), Build::keyword($action), Build::separated(array_map(static fn ($column): Tree => Expressions::write($column), $columns)), ...$options]);
    }
}
