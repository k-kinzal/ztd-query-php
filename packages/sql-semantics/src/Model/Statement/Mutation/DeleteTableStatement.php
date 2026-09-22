<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Mutation;

use Override;

/**
 * Delete one table.
 *
 * @visibility public
 */
final class DeleteTableStatement extends \SqlSemantics\Model\Statement\DeleteStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\TableUse $target,
        ?\SqlSemantics\Model\Expression $where = null,
        array $outputs = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        public readonly array $orderBy = [],
        public readonly ?\SqlSemantics\Model\Expression $limit = null,
        public readonly bool $lowPriority = false,
        public readonly bool $ignore = false,
        public readonly bool $quick = false,
    ) {
        parent::__construct($origin, $where, $outputs, $ctes);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        \SqlSemantics\Model\Validation\StatementOperands::relation($target, $origin->dialect);
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->target, $this->where, $this->outputs, $this->ctes, $this->orderBy, $this->limit, $this->lowPriority, $this->ignore, $this->quick);
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
        return $this->changed(new self($this->origin, $this->target, $where, $this->outputs, $this->ctes, $this->orderBy, $this->limit, $this->lowPriority, $this->ignore, $this->quick));
    }
}
