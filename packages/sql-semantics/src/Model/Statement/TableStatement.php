<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed TableStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
  * @example Inspecting TableStatement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, name TEXT)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('TABLE t');
 *     $query instanceof \SqlSemantics\Model\Statement\TableStatement // => true
 */
final class TableStatement extends \SqlSemantics\Model\BoundQuery
{
    /**
     * @var list<\SqlSemantics\Model\OutputColumn> Result columns derived from the operation's operands
     */
    public readonly array $outputs;

    /**
     * @var array{\SqlSemantics\Model\Relation\NamedTableReference|\SqlSemantics\Model\Relation\CteReference} The single TABLE input
     */
    public readonly array $relations;

    /**
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\NamedTableReference|\SqlSemantics\Model\Relation\CteReference $from,
        array $orderBy = [],
        ?\SqlSemantics\Model\Expression $limit = null,
        ?\SqlSemantics\Model\Expression $offset = null,
        bool $withTies = false,
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        \SqlSemantics\Model\Validation\StatementOperands::relation($from, $origin->dialect);
        $this->relations = [$from];
        $this->outputs = \SqlSemantics\Model\Query\DerivedResults::table($origin, $from);
        parent::__construct($origin, $ctes, \SqlSemantics\Model\Query\Ordering\ResultOrdering::bind($orderBy, $this->outputs), $limit, $offset, $withTies);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Table;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->from, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes);
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

     * Changes the source table and derives its output columns from the supplied schema.

     */
    public function withTable(\SqlSemantics\Schema\TableDefinition $table): self
    {
        $name = new \SqlSemantics\Model\Relation\QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]);
        $relation = $this->from instanceof \SqlSemantics\Model\Relation\OnlyTableReference
            ? new \SqlSemantics\Model\Relation\OnlyTableReference($this->from->id, $this->scopeId, $table, $name, null, $this->from->source)
            : new \SqlSemantics\Model\Relation\TableReference($this->from->id, $this->scopeId, $table, $name, null, $this->from->source);
        return $this->changed(new self($this->origin, $relation, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }

    /**

     * @param list<\SqlSemantics\Model\Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->from, $orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }
}
