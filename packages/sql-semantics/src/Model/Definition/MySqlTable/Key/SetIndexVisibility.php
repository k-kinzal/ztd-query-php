<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Makes an index visible or invisible to the optimizer (MySQL 8.0 and later).
 * @visibility public
 * @example Hiding an index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, KEY ix (id))');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER INDEX ix INVISIBLE');
 *     [$statement->alterations[0]->index, $statement->alterations[0]->visible] // => ['ix', false]
 */
final class SetIndexVisibility implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $index, public readonly bool $visible)
    {
        AlterationInvariant::name($index);
    }
}
