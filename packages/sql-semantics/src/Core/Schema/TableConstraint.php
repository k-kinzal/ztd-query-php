<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Schema;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;

/**
 * A declared integrity condition, retaining its complete syntax for downstream consumers.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Schema\TableConstraint $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class TableConstraint
{
    /**
     * @param ConstraintKind $kind Integrity condition
     * @param list<string> $columns Local columns, in declaration order
     * @param Element $source Typed constraint declaration, including actions and deferrability
     * @param string|null $name Explicit constraint name
     * @param list<string> $referencedTable Qualified foreign table name
     * @param list<string> $referencedColumns Referenced key columns; empty means the primary key
     * @param Element|null $expression Typed CHECK expression
     * @throws InvalidArgumentException When supplied state violates its invariants
     */
    public function __construct(
        public readonly ConstraintKind $kind,
        public readonly array $columns,
        public readonly Element $source,
        public readonly ?string $name = null,
        public readonly array $referencedTable = [],
        public readonly array $referencedColumns = [],
        public readonly ?Element $expression = null,
        public readonly bool $inline = false,
        public readonly bool $descending = false,
    ) {
        Invariant::names($columns);
        Invariant::names($referencedTable);
        Invariant::names($referencedColumns);
        Invariant::elements($source, $expression);
        Invariant::ensure(($kind === ConstraintKind::Check) === ($expression !== null), 'Only a CHECK constraint carries a predicate.');
        Invariant::ensure($kind === ConstraintKind::ForeignKey || ($referencedTable === [] && $referencedColumns === []), 'Only foreign keys carry a reference.');
        Invariant::ensure($kind !== ConstraintKind::ForeignKey || $referencedTable !== [], 'A foreign key needs a referenced table.');
    }
}
