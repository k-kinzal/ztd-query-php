<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Write\ConflictAction;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Common insertion destination and conflict policy; each input form has its own concrete type.
 * @visibility public
  * @example Inspecting InsertStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('INSERT INTO t(id,n) VALUES(1,2)');
 *     $statement instanceof \SqlSemantics\Model\Statement\InsertStatement // => true
 */
abstract class InsertStatement extends BoundStatement implements ResultStatement
{
    /**
     * Dialect-specific insertion priority, override, and conflict policy.
     */
    public readonly \SqlSemantics\Model\Write\Policy\InsertPolicy $policy;

    /**
     * @param list<OutputColumn> $outputs
     * @param list<ConflictAction> $conflicts
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly Insertion $insertion,
        public readonly InsertMode $mode,
        public readonly array $outputs = [],
        public readonly array $conflicts = [],
        public readonly ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        ?\SqlSemantics\Model\Write\Policy\InsertPolicy $policy = null,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\StatementOperands::ctes($ctes, $origin->dialect);
        if ($mode === InsertMode::Replace && $origin->dialect === \SqlSemantics\Dialect::PostgreSql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('PostgreSQL does not have the REPLACE insertion mode.');
        }
        $this->policy = $policy ?? match ($origin->dialect) {
            \SqlSemantics\Dialect::MySql => new \SqlSemantics\Model\Write\Policy\MySqlInsertion(),
            \SqlSemantics\Dialect::PostgreSql => new \SqlSemantics\Model\Write\Policy\PostgreSqlInsertion(),
            \SqlSemantics\Dialect::Sqlite => new \SqlSemantics\Model\Write\Policy\SqliteInsertion(),
        };
        if ($this->policy->dialect() !== $origin->dialect) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An insertion policy must use the statement dialect.');
        }
        \SqlSemantics\Model\Validation\StatementOperands::outputs($outputs, $origin->dialect, true);
        Collections::objects($conflicts, ConflictAction::class);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return $this->mode === InsertMode::Insert ? StatementKind::Insert : StatementKind::Replace;
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

     * @param list<OutputColumn> $outputs

     */
    abstract public function withReturning(array $outputs): static;
    /**
     * Identifies the storage relation affected by this operation.
     *
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    public function affectedTables(): array
    {
        return [$this->insertion->target];
    }

}
