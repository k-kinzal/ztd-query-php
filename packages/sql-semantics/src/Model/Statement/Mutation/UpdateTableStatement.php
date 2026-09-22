<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Mutation;

use Override;

/**
 * Update one table.
 *
 * @visibility public
 */
final class UpdateTableStatement extends \SqlSemantics\Model\Statement\UpdateStatement
{
    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\TableUse $target,
        array $writes,
        ?\SqlSemantics\Model\Expression $where = null,
        array $outputs = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        public readonly array $orderBy = [],
        public readonly ?\SqlSemantics\Model\Expression $limit = null,
        public readonly \SqlSemantics\Model\Write\Policy\ConstraintResponse $onViolation = \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default,
        public readonly bool $lowPriority = false,
        public readonly bool $ignore = false,
    ) {
        parent::__construct($origin, $writes, $where, $outputs, $ctes);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->target, $this->writes, $this->where, $this->outputs, $this->ctes, $this->orderBy, $this->limit, $this->onViolation, $this->lowPriority, $this->ignore);
    }

    /**
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    #[Override]
    public function affectedTables(): array
    {
        return [$this->target];
    }



    /**
     * Rebinds an immutable predicate replacement in this operation's scope.
     */
    #[Override]
    public function withWhere(?\SqlSemantics\Model\Expression $where): static
    {
        return $this->changed(new self($this->origin, $this->target, $this->writes, $where, $this->outputs, $this->ctes, $this->orderBy, $this->limit, $this->onViolation, $this->lowPriority, $this->ignore));
    }

    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     */
    #[Override]
    public function withAssignments(array $writes): static
    {
        return $this->changed(new self($this->origin, $this->target, $writes, $this->where, $this->outputs, $this->ctes, $this->orderBy, $this->limit, $this->onViolation, $this->lowPriority, $this->ignore));
    }
}
