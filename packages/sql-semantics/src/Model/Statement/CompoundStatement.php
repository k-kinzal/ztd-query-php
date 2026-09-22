<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed CompoundStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class CompoundStatement extends \SqlSemantics\Model\BoundQuery
{
    /**
     * @var list<\SqlSemantics\Model\OutputColumn> Result columns derived from the operation's operands
     */
    public readonly array $outputs;

    /**
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\BoundQuery $left,
        public readonly \SqlSemantics\Model\BoundQuery $right,
        public readonly \SqlSemantics\Model\Query\SetOperator $setOperator,
        array $orderBy = [],
        ?\SqlSemantics\Model\Expression $limit = null,
        ?\SqlSemantics\Model\Expression $offset = null,
        bool $withTies = false,
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        \SqlSemantics\Model\Validation\SetOperands::check($origin->dialect, $left, $right);
        $this->outputs = \SqlSemantics\Model\Query\DerivedResults::compound($origin, $left, $right, $setOperator);
        parent::__construct($origin, $ctes, \SqlSemantics\Model\Query\Ordering\ResultOrdering::bind($orderBy, $this->outputs), $limit, $offset, $withTies);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return match ($this->setOperator) {
            \SqlSemantics\Model\Query\SetOperator::Union, \SqlSemantics\Model\Query\SetOperator::UnionAll => StatementKind::Union,
            \SqlSemantics\Model\Query\SetOperator::Intersect, \SqlSemantics\Model\Query\SetOperator::IntersectAll => StatementKind::Intersect,
            \SqlSemantics\Model\Query\SetOperator::Except, \SqlSemantics\Model\Query\SetOperator::ExceptAll => StatementKind::Except,
        };
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->left, $this->right, $this->setOperator, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes);
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
     * Replaces one set operand and recomputes result width and common types.

     */
    public function withLeft(\SqlSemantics\Model\BoundQuery $left): self
    {
        return $this->changed(new self($this->origin, $left, $this->right, $this->setOperator, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }

    /**
     * Replaces the right operand and recomputes result width and common types.

     */
    public function withRight(\SqlSemantics\Model\BoundQuery $right): self
    {
        return $this->changed(new self($this->origin, $this->left, $right, $this->setOperator, $this->orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }

    /**

     * @param list<\SqlSemantics\Model\Ordering> $orderBy

     */
    #[Override]
    public function withOrderBy(array $orderBy): static
    {
        return $this->changed(new self($this->origin, $this->left, $this->right, $this->setOperator, $orderBy, $this->limit, $this->offset, $this->withTies, $this->ctes));
    }
}
