<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Adds a declared column together with the integrity constraints written on it; foreign tables may add wrapper options.
 * @visibility public
 * @example Reading the added column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD COLUMN IF NOT EXISTS n integer NOT NULL DEFAULT 0 CHECK (n >= 0)');
 *     $statement->actions[0]->column->name // => 'n'
 *     $statement->actions[0]->ifNotExists // => true
 *     count($statement->actions[0]->constraints) // => 1
 */
final class AddColumn implements RelationAction
{
    /**
     * @param list<TableConstraint> $constraints
     * @param list<ForeignOption> $options
     * @throws InvalidStructure
     */
    public function __construct(public readonly ColumnDefinition $column, public readonly array $constraints = [], public readonly bool $ifNotExists = false, public readonly array $options = [])
    {
        if ($column->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('An added column requires a PostgreSQL type declaration.');
        }
        Collections::objects($constraints, TableConstraint::class);
        Collections::objects($options, ForeignOption::class);
    }
}
