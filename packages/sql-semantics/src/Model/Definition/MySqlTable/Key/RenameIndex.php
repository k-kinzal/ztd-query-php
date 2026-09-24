<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames an index (MySQL 5.7 and later).
 * @visibility public
 * @example Renaming an index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, KEY ix (id))');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t RENAME INDEX ix TO ix_id');
 *     [$statement->alterations[0]->index, $statement->alterations[0]->newName] // => ['ix', 'ix_id']
 */
final class RenameIndex implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $index, public readonly string $newName)
    {
        AlterationInvariant::name($index);
        AlterationInvariant::name($newName);
    }
}
