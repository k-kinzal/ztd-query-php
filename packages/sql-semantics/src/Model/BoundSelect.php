<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use Override;

/**
 * A SELECT projection with its input relations, predicates, grouping, and ordering.
 * @visibility public
 * @example Reading a selected value
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t');
 *     $statement->outputs[0]->name // => 'id'
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
     * @param list<Expression|Query\Grouping\GroupingConstruct|Query\Grouping\DescendingGroupKey> $groupBy Grouping keys and grouping-set constructs
     * @param list<Query\Optimization\OptimizerHint> $hints
     * @param list<Query\Optimization\SelectOption> $options Distinct MySQL query block options, in written order
     * @param list<Query\Locking\RowLock> $locks
     * @param list<Window\Definition> $windows
     * @param bool $distinctGroupingSets PostgreSQL GROUP BY DISTINCT: duplicate grouping sets form their groups once
     * @param Expression|null $qualify MySQL 8.3+ QUALIFY: the predicate evaluated after window functions
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
        public readonly array $options = [],
        public readonly bool $distinctGroupingSets = false,
        public readonly ?Expression $qualify = null,
    ) {
        parent::__construct($origin, $ctes, $orderBy, $limit, $offset, $withTies);
        Validation\StatementOperands::relation($from, $origin->dialect);
        $this->relations = Relation\Joining\Inputs::tables($from);
        Validation\StatementOperands::outputs($outputs, $origin->dialect);
        if ($outputs === [] && $origin->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new Validation\InvalidStructure('A SELECT projection requires at least one output outside PostgreSQL.');
        }
        Validation\StatementOperands::expressions([$where, $having, $qualify], $origin->dialect);
        if ($qualify !== null && ($origin->dialect !== \SqlSemantics\Dialect::MySql || in_array($origin->context?->schema()->grammarVersion, Query\Grouping\GroupingRules::WITHOUT_CUBE, true))) {
            throw new Validation\InvalidStructure('QUALIFY requires MySQL 8.3 or later.');
        }
        Validation\Collections::objects($orderBy, Ordering::class);
        Query\Grouping\GroupingRules::validate($groupBy, $origin, $distinctGroupingSets);
        Validation\Collections::objects($windows, Window\Definition::class);
        Validation\Collections::objects($hints, Query\Optimization\OptimizerHint::class);
        Query\Optimization\SelectOptions::validate($options, $origin);
        Query\Locking\LockPlacement::validate($locks, $origin, $this->relations);
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
        return new static($origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify);
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
        return $this->changed(new self($this->origin, $this->from, $outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }

    /**
     * Sets or removes the row predicate and validates its names and type.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }

    /**
     * Replaces the grouping keys and grouping-set constructs; an empty list removes the grouping.
     *
     * @param list<Expression|Query\Grouping\GroupingConstruct|Query\Grouping\DescendingGroupKey> $expressions
     */
    public function withGroupBy(array $expressions): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $expressions, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }

    /**
     * Sets or removes the predicate evaluated after grouping.
     */
    public function withHaving(?Expression $having): self
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }

    /**
     * Changes the complete input relation, including joins and derived queries.
     */
    public function withFrom(TableUse|Join|null $from): self
    {
        return $this->changed(new self($this->origin, $from, $this->outputs, $this->where, $this->quantifier, $this->orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }

    /**

     * @param list<Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->from, $this->outputs, $this->where, $this->quantifier, $orderBy, $this->limit, $this->offset, $this->groupBy, $this->having, $this->ctes, $this->withTies, $this->windows, $this->locks, $this->hints, $this->options, $this->distinctGroupingSets, $this->qualify));
    }
}
