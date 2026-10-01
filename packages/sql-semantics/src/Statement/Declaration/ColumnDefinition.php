<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;

/**
 * A column declaration, before a query can change its nullability.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Statement\Declaration\ColumnDefinition $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 * @example A default clause and its value must be supplied together
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $column = $semantics->analyze('CREATE TABLE t(a INT DEFAULT 1)', [])->resolution->declarations[0]->columns[0];
 *     new \SqlSemantics\Statement\Declaration\ColumnDefinition($column->name, $column->type, $column->nullability, $column->source, defaultExpression: $column->defaultExpression) // throws \InvalidArgumentException
 *
 * @visibility public
 */
final class ColumnDefinition
{
    /**
     * @param string $name Resolved column name
     * @param TypeDescriptor $type Declared database type
     * @param Nullability $nullability Declaration-level NULL allowance
     * @param Element $source Typed column declaration
     * @param Element|null $defaultExpression Typed default clause, evaluated on insertion
     * @param list<Element> $attributes Complete typed column attributes, in declaration order
     * @param Element|null $defaultValue The default value expression without the DEFAULT envelope
     * @throws InvalidArgumentException When supplied state violates its invariants
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Element $source,
        public readonly ?Element $defaultExpression = null,
        public readonly ?ColumnGeneration $generation = null,
        public readonly ?Element $collation = null,
        public readonly bool $autoIncrement = false,
        public readonly array $attributes = [],
        public readonly ?Element $defaultValue = null,
    ) {
        Invariant::members($attributes, Element::class);
        Invariant::elements($source, $defaultExpression, $defaultValue, $collation, ...$attributes);
        Invariant::ensure(($defaultValue === null) === ($defaultExpression === null), 'A default clause and its value must be supplied together.');
    }

    /**
     * Refines a column's NULL fact while preserving all declaration data.
     * @throws InvalidArgumentException When supplied state violates its invariants
     */
    public function withNullability(Nullability $nullability): self
    {
        return new self($this->name, $this->type, $nullability, $this->source, $this->defaultExpression, $this->generation, $this->collation, $this->autoIncrement, $this->attributes, $this->defaultValue);
    }
}
