<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed ValuesStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
  * @example Inspecting ValuesStatement
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('VALUES (1), (2.5), (NULL)');
 *     $query instanceof \SqlSemantics\Model\Statement\ValuesStatement // => true
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
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        array $rows,
        array $orderBy = [],
        ?\SqlSemantics\Model\Expression $limit = null,
        ?\SqlSemantics\Model\Expression $offset = null,
        bool $withTies = false,
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        \SqlSemantics\Model\Validation\RowShape::rows($rows, $origin->dialect);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        $this->rows = \SqlSemantics\Model\Validation\Collections::nonEmpty($rows);
        $this->outputs = \SqlSemantics\Model\Query\DerivedResults::rows($origin, $this->rows);
        parent::__construct($origin, $ctes, \SqlSemantics\Model\Query\Ordering\ResultOrdering::bind($orderBy, $this->outputs), $limit, $offset, $withTies);
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
        return new static($origin, $this->rows, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes);
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
        return $this->changed(new self($this->origin, $rows, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }

    /**

     * @param list<\SqlSemantics\Model\Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->rows, $orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }
}
