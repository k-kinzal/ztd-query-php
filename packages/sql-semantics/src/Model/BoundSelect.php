<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use Override;

/**
 * Typed BoundSelect operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class BoundSelect extends BoundQuery
{
    /**
     * @var list<TableUse> Relation occurrences derived from the complete FROM input
     */
    public readonly array $relations;

    /**
     * @param list<OutputColumn> $outputs
     * @param list<Ordering> $orderBy
     * @param list<Expression> $groupBy
     * @param list<Query\Optimization\OptimizerHint> $hints
     * @param list<Query\Locking\RowLock> $locks
     * @param list<Window\Definition> $windows
     * @visibility SqlSemantics
     * @throws Validation\InvalidStructure
     */
    public function __construct(
        Statement\Origin $origin,
        public readonly TableUse|Join|null $from,
        public readonly array $outputs,
        public readonly ?Expression $where,
        public readonly Query\Quantifier $quantifier,
        array $orderBy,
        ?Expression $limit,
        ?Expression $offset,
        public readonly array $groupBy = [],
        public readonly ?Expression $having = null,
        ?Query\WithClause $ctes = null,
        bool $withTies = false,
        public readonly array $windows = [],
        public readonly array $locks = [],
        public readonly array $hints = [],
    ) {
        parent::__construct($origin, $ctes, $orderBy, $limit, $offset, $withTies);
        $this->relations = Relation\Joining\Inputs::tables($from);
        Validation\StatementOperands::outputs($outputs, $origin->dialect);
        Validation\StatementOperands::expressions([$where, $having, ...$groupBy], $origin->dialect);
        Validation\Collections::objects($orderBy, Ordering::class);
        Validation\Collections::objects($groupBy, Expression::class);
        Validation\Collections::objects($windows, Window\Definition::class);
        Validation\Collections::objects($hints, Query\Optimization\OptimizerHint::class);
        Validation\Collections::objects($locks, Query\Locking\RowLock::class);
        if ($locks !== [] && $origin->dialect === \SqlSemantics\Dialect::Sqlite) {
            throw new Validation\InvalidStructure('SQLite SELECT does not have locking clauses.');
        }
        foreach ($locks as $lock) {
            if ($origin->dialect === \SqlSemantics\Dialect::MySql && in_array($lock->strength, [Query\Locking\LockStrength::KeyShare, Query\Locking\LockStrength::NoKeyUpdate], true)) {
                throw new Validation\InvalidStructure('This lock strength is specific to PostgreSQL.');
            }
            if ($lock instanceof Query\Locking\NamedRowLock) {
                foreach ($lock->relations as $relation) {
                    if ($relation instanceof TableUse && !in_array($relation, $this->relations, true)) {
                        throw new Validation\InvalidStructure('A named lock target must belong to this query input.');
                    }
                }
            }
        }
    }

    #[Override]
    protected function operation(): Statement\StatementKind
    {
        return Statement\StatementKind::Select;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Statement\Origin $origin): static
    {
        return new static($origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints);
    }

    /**

     * @return list<OutputColumn>

     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->outputs;
    }


    /**
     * @param list<OutputColumn> $outputs Ordered replacement result columns
     */
    public function withOutputs(array $outputs): self
    {
        return $this->changed(new self($this->origin, $this->from, $outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }

    /**
     * Sets or removes the row predicate and validates its names and type.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }

    /**
     * @param list<Expression> $expressions
     */
    public function withGroupBy(array $expressions): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $expressions, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }

    /**
     * Sets or removes the predicate evaluated after grouping.
     */
    public function withHaving(?Expression $having): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }

    /**
     * Changes the complete input relation, including joins and derived queries.
     */
    public function withFrom(TableUse|Join|null $from): self
    {
        return $this->changed(new self($this->origin, $from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }

    /**

     * @param list<Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints));
    }
}
