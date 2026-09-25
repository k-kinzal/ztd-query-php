<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed ValuesStatement operands, with the row-locking clauses MySQL accepts after a VALUES query block; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Inspecting ValuesStatement
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('VALUES (1), (2.5), (NULL)');
 *     $query instanceof \SqlSemantics\Model\Statement\ValuesStatement // => true
 * @example Reading the row locks of a MySQL VALUES query block
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('VALUES ROW(1) FOR UPDATE');
 *     $query->locks[0]->strength // => \SqlSemantics\Model\Query\Locking\LockStrength::Update
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => 'VALUES ROW(1) FOR UPDATE'
 */
final class ValuesStatement extends \SqlSemantics\Model\BoundQuery
{
    /**
     * @var list<\SqlSemantics\Model\OutputColumn> Result columns derived from the operation's operands
     */
    public readonly array $outputs;

    /**
     * @var non-empty-list<list<\SqlSemantics\Model\Expression>> Validated ordered operands
     */
    public readonly array $rows;

    /**
     * @param list<list<\SqlSemantics\Model\Expression>> $rows
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @param list<\SqlSemantics\Model\Query\Locking\RowLock> $locks Row-locking clauses of the query block; only MySQL accepts them on VALUES
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        array $rows,
        array $orderBy = [],
        ?\SqlSemantics\Model\Expression $limit = null,
        ?\SqlSemantics\Model\Expression $offset = null,
        bool $withTies = false,
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        public readonly array $locks = [],
    ) {
        \SqlSemantics\Model\Validation\RowShape::rows($rows, $origin->dialect);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        $this->rows = \SqlSemantics\Model\Validation\Collections::nonEmpty($rows);
        $this->outputs = \SqlSemantics\Model\Query\DerivedResults::rows($origin, $this->rows);
        parent::__construct($origin, $ctes, \SqlSemantics\Model\Query\Ordering\ResultOrdering::bind($orderBy, $this->outputs), $limit, $offset, $withTies);
        \SqlSemantics\Model\Query\Locking\LockPlacement::validate($locks, $origin, []);
        if ($locks !== [] && $origin->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only MySQL accepts a locking clause on a VALUES query block.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Values;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->rows, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes, $this->locks);
    }

    /**

     * @return list<\SqlSemantics\Model\OutputColumn>

     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->outputs;
    }


    /**
     * @param non-empty-list<list<\SqlSemantics\Model\Expression>> $rows Equal-width replacement rows
     */
    public function withRows(array $rows): self
    {
        return $this->changed(new self($this->origin, $rows, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes, $this->locks));
    }

    /**

     * @param list<\SqlSemantics\Model\Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->rows, $orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes, $this->locks));
    }

    /**
     * Replaces the row-locking clauses; an empty list removes them.
     *
     * @param list<\SqlSemantics\Model\Query\Locking\RowLock> $locks
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withLocks(array $locks): self
    {
        return $this->changed(new self($this->origin, $this->rows, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes, $locks));
    }
}
