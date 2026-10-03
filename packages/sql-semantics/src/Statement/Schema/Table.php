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
     * Optional SQLite row identity, kept outside the explicit column list.
     */
    public readonly ?SqliteRowIdentifier $rowIdentifier;

    /**
     * Preserves the supplied column objects and their declaration order.
     */
    public function __construct(public readonly QualifiedName $name, Column|SqliteRowIdentifier ...$columns)
    {
        $explicit = [];
        $rowIdentifier = null;
        foreach ($columns as $column) {
            if ($column instanceof SqliteRowIdentifier) {
                assert($rowIdentifier === null, 'A table has at most one row identifier.');
                $rowIdentifier = $column;
            } else {
                $explicit[] = $column;
            }
        }
        $this->columns = $explicit;
        $this->rowIdentifier = $rowIdentifier;
        assert($rowIdentifier === null || ($rowIdentifier->alias === null
            ? !in_array($rowIdentifier->column, $explicit, true)
            : in_array($rowIdentifier->alias, $explicit, true)), 'A rowid alias must be an explicit column of this same declaration; an implicit rowid stays outside that list.');
    }

    /**
     * Returns every matching declaration so duplicate names cannot silently choose a winner.
     * @return list<Column>
     */
    public function matchingColumns(string $name, Comparison $comparison): array
    {
        $declared = array_values(array_filter($this->columns, static fn (Column $column): bool => $comparison->equal($column->name->value, $name)));
        return $declared === [] && $this->rowIdentifier !== null && $this->rowIdentifier->matches($name, $comparison) ? [$this->rowIdentifier->column] : $declared;
    }

    /**
     * Checks identity membership, including the separate implicit row identifier.
     */
    public function ownsColumn(Column $column): bool
    {
        return in_array($column, $this->columns, true) || $this->rowIdentifier?->column === $column;
    }

    /**
     * Adds a declaration persistently, retaining every existing column identity.
     */
    public function withColumn(Column $column): self
    {
        return new self($this->name, ...[...$this->columns, $column, ...($this->rowIdentifier === null ? [] : [$this->rowIdentifier])]);
    }
}
