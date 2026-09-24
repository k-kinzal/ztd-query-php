<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes a column; MySQL accepts and ignores RESTRICT and CASCADE.
 * @visibility public
 * @example Dropping a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP COLUMN n CASCADE');
 *     $statement->alterations[0]->column // => 'n'
 *     $statement->alterations[0]->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class DropColumn implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        AlterationInvariant::name($column);
    }
}
