<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Moves the altered table to a new name, possibly in another database.
 * @visibility public
 * @example Renaming the altered table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t RENAME TO archive.t');
 *     $statement->alterations[0]->newName->parts // => ['archive', 't']
 * @example Rejecting a three-part name
 *     new \SqlSemantics\Model\Definition\MySqlTable\Table\RenameTable(new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameTable implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $newName)
    {
        CatalogInvariant::name($newName, 2);
    }
}
