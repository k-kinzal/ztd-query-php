<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;

/**
 * Adds or removes the NOT NULL requirement of one column.
 * @visibility public
 * @example Allowing nulls again
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP NOT NULL');
 *     $statement->actions[0]->nullability // => \SqlSemantics\Type\Nullability::MaybeNull
 * @example Rejecting a nullability that a declaration cannot express
 *     new \SqlSemantics\Model\Definition\Relation\Column\SetColumnNullability('id', \SqlSemantics\Type\Nullability::Unknown); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetColumnNullability implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly Nullability $nullability)
    {
        ColumnInvariant::column($column);
        if (!in_array($nullability, [Nullability::NotNull, Nullability::MaybeNull], true)) {
            throw new InvalidStructure('A column declaration is either NOT NULL or nullable.');
        }
    }
}
