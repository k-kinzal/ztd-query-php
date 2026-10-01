<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * A table declaration and its ordered column identities, without schema history.
 * @visibility public
 * @example Describing a declaration context
 *     $table = new \SqlSemantics\Statement\Schema\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     $table->name->toString() // => 'users'
 */
final class Table
{
    /**
     * @var list<Column>
     */
    public readonly array $columns;

    /**
     * Preserves the supplied column objects and their declaration order.
     */
    public function __construct(public readonly QualifiedName $name, Column ...$columns)
    {
        $this->columns = array_values($columns);
    }

    /**
     * Returns every matching declaration so duplicate names cannot silently choose a winner.
     * @return list<Column>
     */
    public function matchingColumns(string $name, Comparison $comparison): array
    {
        return array_values(array_filter($this->columns, static fn (Column $column): bool => $comparison->equal($column->name->value, $name)));
    }

    /**
     * Adds a declaration persistently, retaining every existing column identity.
     */
    public function withColumn(Column $column): self
    {
        return new self($this->name, ...[...$this->columns, $column]);
    }
}
