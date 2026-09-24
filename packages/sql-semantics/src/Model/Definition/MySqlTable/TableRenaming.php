<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One table moved to a new name, possibly in another database.
 * @visibility public
 * @example Reading a renaming pair
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('RENAME TABLE t TO archive.t');
 *     $statement->renamings[0]->table->declaration->name // => 't'
 *     $statement->renamings[0]->newName->parts // => ['archive', 't']
 * @example Rejecting an aliased source table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $table = (new \SqlSemantics\Binder($schema))->bind('SELECT * FROM t AS a')->from;
 *     new \SqlSemantics\Model\Definition\MySqlTable\TableRenaming($table, new \SqlSemantics\Model\Relation\QualifiedName(['u'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TableRenaming
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableReference $table, public readonly QualifiedName $newName)
    {
        if ($table->alias !== null) {
            throw new InvalidStructure('A renamed table cannot use a query alias.');
        }
        if (count($table->name->parts) > 2 || count($newName->parts) > 2) {
            throw new InvalidStructure('A MySQL table name has at most a database and a table component.');
        }
    }
}
