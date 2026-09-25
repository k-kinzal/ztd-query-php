<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A column declaration with a typed value source and declaration-level nullability.
 *
 * @visibility public
 * @example Reading and renaming a column declaration
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)')->tables[0]->columns[0];
 *     $column->name // => 'id'
 *     $column->nullConflict // => \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default
 *     $column->nullability // => \SqlSemantics\Type\Nullability::NotNull
 *     $column->nullDeclared // => false
 *     $column->withName('key')->name // => 'key'
 *     $column->name // => 'id'
 */
final class ColumnDefinition
{
    /**
     * @param \SqlSemantics\Model\Write\Policy\ConstraintResponse $nullConflict SQLite ON CONFLICT resolution of the NOT NULL constraint; Default when none is declared
     * @param bool $nullDeclared Whether the declaration writes NULL for a column that allows NULL, rather than leaving it implied
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node $source,
        public readonly Column\Generation $generation = new Column\SuppliedColumn(),
        public readonly Column\Attributes $attributes = new Column\Attributes(),
        public readonly \SqlSemantics\Model\Write\Policy\ConstraintResponse $nullConflict = \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default,
        public readonly bool $nullDeclared = false,
    ) {
        if ($nullDeclared && $nullability === Nullability::NotNull) {
            throw new InvalidStructure('A column declared NULL cannot be NOT NULL.');
        }
        Constraint\ConflictClause::check($nullConflict, $type->dialect);
        if ($nullConflict !== \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default && $nullability !== Nullability::NotNull) {
            throw new InvalidStructure('An ON CONFLICT resolution for NULL values requires a NOT NULL column.');
        }
        foreach ($generation->expressions() as $expression) {
            if ($expression->type->dialect !== $type->dialect) {
                throw new InvalidStructure('A column and its generation expressions must use the same dialect.');
            }
        }
    }

    /**
     * Renames the declaration; statement transformations rebind dependent expressions.
     */
    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->nullability, $this->source, $this->generation, $this->attributes, $this->nullConflict, $this->nullDeclared);
    }

    /**
     * Replaces the declared type without mutating the original declaration.
     */
    public function withType(TypeDescriptor $type): self
    {
        return new self($this->name, $type, $this->nullability, $this->source, $this->generation, $this->attributes, $this->nullConflict, $this->nullDeclared);
    }

    /**
     * Replaces the value source, retaining the declared type and NULL policy.
     */
    public function withGeneration(Column\Generation $generation): self
    {
        return new self($this->name, $this->type, $this->nullability, $this->source, $generation, $this->attributes, $this->nullConflict, $this->nullDeclared);
    }
}
