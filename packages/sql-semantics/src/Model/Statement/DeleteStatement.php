<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Common delete effects; each concrete form owns its mandatory table inputs.
 *
 * @visibility public
 */
abstract class DeleteStatement extends \SqlSemantics\Model\BoundStatement implements \SqlSemantics\Model\ResultStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly ?\SqlSemantics\Model\Expression $where,
        public readonly array $outputs = [],
        public readonly ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\StatementOperands::ctes($ctes, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::expressions([$where], $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::outputs($outputs, $origin->dialect, true);
    }

    /**
     * Returns the fixed operation identity.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Delete;
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
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    abstract public function affectedTables(): array;

    /**
     * Replaces the row predicate without mutating table inputs.
     */
    abstract public function withWhere(?\SqlSemantics\Model\Expression $where): static;
}
