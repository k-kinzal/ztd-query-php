<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Mutation;

use Override;

/**
 * Update named targets from a joined relation.
 *
 * @visibility public
 */
final class UpdateJoinedStatement extends \SqlSemantics\Model\Statement\UpdateStatement
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\TableUse> Validated ordered operands
     */
    public readonly array $targets;

    /**
     * @param list<\SqlSemantics\Model\TableUse> $targets
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Join $from,
        array $targets,
        array $writes,
        ?\SqlSemantics\Model\Expression $where = null,
        array $outputs = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        public readonly bool $lowPriority = false,
        public readonly bool $ignore = false,
    ) {
        parent::__construct($origin, $writes, $where, $outputs, $ctes);
        \SqlSemantics\Model\Validation\Collections::objects($targets, \SqlSemantics\Model\TableUse::class);
        if ($targets === [] || $origin->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A joined mutation requires named targets and the MySQL dialect.');
        }
        $this->targets = \SqlSemantics\Model\Validation\Collections::nonEmpty($targets);
        foreach ($this->targets as $table) {
            \SqlSemantics\Model\Validation\StatementOperands::relation($table, $origin->dialect);
        }
        \SqlSemantics\Model\Validation\StatementOperands::relation($from, $origin->dialect);
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->from, $this->targets, $this->writes, $this->where, $this->outputs, $this->ctes, $this->lowPriority, $this->ignore);
    }

    /**
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    #[Override]
    public function affectedTables(): array
    {
        return $this->targets;
    }



    /**
     * Rebinds an immutable predicate replacement in this operation's scope.
     */
    #[Override]
    public function withWhere(?\SqlSemantics\Model\Expression $where): static
    {
        return $this->changed(new self($this->origin, $this->from, $this->targets, $this->writes, $where, $this->outputs, $this->ctes, $this->lowPriority, $this->ignore));
    }

    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     */
    #[Override]
    public function withAssignments(array $writes): static
    {
        return $this->changed(new self($this->origin, $this->from, $this->targets, $writes, $this->where, $this->outputs, $this->ctes, $this->lowPriority, $this->ignore));
    }
}
