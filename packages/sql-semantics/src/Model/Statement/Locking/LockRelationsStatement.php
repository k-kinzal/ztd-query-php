<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Locking;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Locking\PostgreSqlLockMode;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Acquires ordered PostgreSQL relation locks in one mode for the current transaction.
 * @visibility public
 * @example Inspecting a transaction lock request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('LOCK TABLE ONLY t IN SHARE MODE NOWAIT');
 *     $statement->mode === \SqlSemantics\Model\Locking\PostgreSqlLockMode::Share // => true
 *     $statement->nowait // => true
 */
final class LockRelationsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<TableReference|OnlyTableReference> Tables in acquisition order
     */
    public readonly array $tables;

    /**
     * @param list<TableReference|OnlyTableReference> $tables Ordered lock targets without aliases
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $tables, public readonly PostgreSqlLockMode $mode = PostgreSqlLockMode::AccessExclusive, public readonly bool $nowait = false)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Transaction relation locks require PostgreSQL.');
        }
        Collections::alternatives($tables, [TableReference::class, OnlyTableReference::class]);
        foreach ($tables as $table) {
            if ($table->alias !== null) {
                throw new InvalidStructure('A relation lock requires an unaliased physical table.');
            }
            StatementOperands::relation($table, $origin->dialect);
        }
        $this->tables = Collections::nonEmpty($tables);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Lock;
    }

    /**
     * Retains acquisition order, conflict mode and wait policy while replacing provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->tables, $this->mode, $this->nowait);
    }

    /**
     * Changes the conflict mode without executing a lock or changing the schema snapshot.
     */
    public function withMode(PostgreSqlLockMode $mode): self
    {
        return $this->changed(new self($this->origin, $this->tables, $mode, $this->nowait));
    }

    /**
     * @param non-empty-list<TableReference|OnlyTableReference> $tables Ordered replacement targets
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->mode, $this->nowait));
    }
}
