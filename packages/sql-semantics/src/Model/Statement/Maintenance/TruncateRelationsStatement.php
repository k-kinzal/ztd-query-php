<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IdentityReset;
use SqlSemantics\Model\Maintenance\ReferencingTables;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * A PostgreSQL complete-table removal request with explicit sequence and reference policies.
 * @visibility public
 * @example Inspecting sequence behavior
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TRUNCATE ONLY t RESTART IDENTITY CASCADE');
 *     $statement->identities === \SqlSemantics\Model\Maintenance\IdentityReset::Restart // => true
 */
final class TruncateRelationsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<TableReference|OnlyTableReference> Explicit ordered targets
     */
    public readonly array $tables;

    /**
     * @param list<TableReference|OnlyTableReference> $tables Explicit physical relations without aliases
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $tables, public readonly IdentityReset $identities = IdentityReset::Continue, public readonly ReferencingTables $references = ReferencingTables::RequireListed)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Multi-relation truncation requires PostgreSQL.');
        }
        Collections::alternatives($tables, [TableReference::class, OnlyTableReference::class]);
        foreach ($tables as $table) {
            if ($table->alias !== null) {
                throw new InvalidStructure('A truncation target cannot have an alias.');
            }
            StatementOperands::relation($table, $origin->dialect);
        }
        $this->tables = Collections::nonEmpty($tables);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Truncate;
    }

    /**
     * Retains explicit targets and policies while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->tables, $this->identities, $this->references);
    }

    /**
     * @param non-empty-list<TableReference|OnlyTableReference> $tables Replacement explicit targets
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->identities, $this->references));
    }

    /**
     * Changes the owned-sequence restart request without evaluating any sequence.
     */
    public function withIdentities(IdentityReset $identities): self
    {
        return $this->changed(new self($this->origin, $this->tables, $identities, $this->references));
    }
}
