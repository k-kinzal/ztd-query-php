<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * A physical table with an optional explicit list of requested index identifiers.
 * @visibility public
 * @example Reading the owning table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $request = (new \SqlSemantics\Binder($schema))->bind('CACHE INDEX t IN DEFAULT');
 *     $request->targets[0]->table->declaration->name // => 't'
 */
final class TableIndexes
{
    /**
     * A null index selection requests every index. NamedIndexes retains an explicit request.
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableReference $table, public readonly ?NamedIndexes $indexes = null)
    {
        StatementOperands::relation($table, Dialect::MySql);
        if ($table->alias !== null) {
            throw new InvalidStructure('An index-cache request requires an unaliased physical table.');
        }
    }
}
