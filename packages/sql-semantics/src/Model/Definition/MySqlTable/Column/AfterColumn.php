<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Places an added, changed, or modified column directly after a named column.
 * @visibility public
 * @example Reading an AFTER placement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD COLUMN n INT AFTER id');
 *     $statement->alterations[0]->position->column // => 'id'
 * @example Rejecting an empty column name
 *     new \SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AfterColumn
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column)
    {
        AlterationInvariant::name($column);
    }
}
