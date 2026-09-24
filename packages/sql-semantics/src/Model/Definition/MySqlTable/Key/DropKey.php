<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes a named index, foreign key, CHECK constraint, or constraint of any kind.
 * @visibility public
 * @example Dropping an index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, KEY ix (id))');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP KEY ix');
 *     [$statement->alterations[0]->name, $statement->alterations[0]->kind] // => ['ix', \SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind::Index]
 */
final class DropKey implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly KeyKind $kind)
    {
        AlterationInvariant::name($name);
    }
}
