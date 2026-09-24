<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests a MySQL 8 ALTER TABLESPACE that changes properties without adding or removing a data file.
 * @visibility public
 * @example Inspecting a property-only change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER TABLESPACE ts ENCRYPTION 'N'");
 *     [$statement->name, $statement->changes->encryption->value] // => ['ts', 'N']
 */
final class AlterTablespaceStatement extends BoundStatement
{
    /**
     * The completion request is always part of the change, so the option list is never empty.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly TablespaceChanges $changes = new TablespaceChanges())
    {
        StorageInvariant::names($origin, $name);
        StorageInvariant::modern($origin, 'ALTER TABLESPACE without a data file');
        StorageInvariant::options($origin, $changes);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the alteration while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->changes);
    }

    /**
     * Replaces the altered tablespace.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->changes));
    }

    /**
     * Replaces the requested property changes.
     * @throws InvalidStructure
     */
    public function withChanges(TablespaceChanges $changes): self
    {
        return $this->changed(new self($this->origin, $this->name, $changes));
    }
}
