<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A relation processed by VACUUM or ANALYZE, with the columns whose statistics are collected; no columns means all.
 * @visibility public
 * @example Reading a target with columns
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE t (b)');
 *     [$statement->targets[0]->table->declaration->name, $statement->targets[0]->columns] // => ['t', ['b']]
 */
final class MaintenanceTarget
{
    /**
     * @param TableReference $table Resolved or diagnosed relation
     * @param list<string> $columns Column names in written order
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableReference $table, public readonly array $columns = [])
    {
        if ($table->alias !== null) {
            throw new InvalidStructure('A maintenance target cannot use a query alias.');
        }
        if (in_array('', $columns, true)) {
            throw new InvalidStructure('Maintenance target columns require nonempty names in written order.');
        }
    }
}
