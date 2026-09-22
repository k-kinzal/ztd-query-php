<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Common update effects; each concrete form owns its mandatory table inputs.
 *
 * @visibility public
 */
abstract class UpdateStatement extends \SqlSemantics\Model\BoundStatement implements \SqlSemantics\Model\ResultStatement
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Write\Assignment> Validated ordered operands
     */
    public readonly array $writes;

    /**
     * @param list<\SqlSemantics\Model\Write\Assignment> $writes
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        array $writes,
        public readonly ?\SqlSemantics\Model\Expression $where,
        public readonly array $outputs = [],
        public readonly ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\StatementOperands::ctes($ctes, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::expressions([$where], $origin->dialect);
        \SqlSemantics\Model\Validation\Collections::objects($writes, \SqlSemantics\Model\Write\Assignment::class);
        if ($writes === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An UPDATE requires an assignment.');
        }
        \SqlSemantics\Model\Validation\StatementOperands::outputs($outputs, $origin->dialect, true);
        $this->writes = \SqlSemantics\Model\Validation\Collections::nonEmpty($writes);
    }

    /**
     * Returns the fixed operation identity.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Update;
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

    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     */
    abstract public function withAssignments(array $writes): static;
}
