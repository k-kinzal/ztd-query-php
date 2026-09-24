<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Sampling\SamplingMethod;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\TableUse;

/**
 * Checks the dialect of the access clauses written after a table name: index hints and partition selection belong to
 * MySQL, index directives to SQLite, and samples to PostgreSQL and MySQL, whose sample is a built-in method with one
 * percentage and no seed.
 *
 * @visibility SqlSemantics
 */
final class TableAccess
{
    /**
     * Rejects an access clause the owning dialect does not have.
     * @throws InvalidStructure
     */
    public static function check(TableUse $table, Dialect $dialect): void
    {
        if ($table instanceof TableReference) {
            if (($table->indexHints !== [] || $table->partitions !== null) && $dialect !== Dialect::MySql) {
                throw new InvalidStructure('Index hints and partition selection require MySQL.');
            }
            if ($table->indexing !== null && $dialect !== Dialect::Sqlite) {
                throw new InvalidStructure('INDEXED BY and NOT INDEXED require SQLite.');
            }
        }
        $sample = $table instanceof NamedTableReference ? $table->sample : null;
        if ($sample === null) {
            return;
        }
        if ($dialect === Dialect::Sqlite || ($dialect === Dialect::MySql && (!$sample->method instanceof SamplingMethod || $sample->repeatable !== null))) {
            throw new InvalidStructure('TABLESAMPLE requires PostgreSQL, or a MySQL built-in method without REPEATABLE.');
        }
        StatementOperands::expressions([...$sample->arguments, $sample->repeatable], $dialect);
    }
}
