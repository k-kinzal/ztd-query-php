<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Definition\Storage\TablespaceAccess;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests the MySQL 5.x ALTER TABLESPACE form that switches the access mode.
 * @visibility public
 * @example Inspecting the requested access mode
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER TABLESPACE ts READ_ONLY');
 *     $statement->access->value // => 'READ_ONLY'
 */
final class SetTablespaceAccessStatement extends BoundStatement
{
    /**
     * Available only in MySQL 5.x grammars.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly TablespaceAccess $access)
    {
        StorageInvariant::names($origin, $name);
        StorageInvariant::older($origin, 'A tablespace access mode');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the access request while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->access);
    }

    /**
     * Replaces the altered tablespace.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->access));
    }

    /**
     * Replaces the requested access mode.
     * @throws InvalidStructure
     */
    public function withAccess(TablespaceAccess $access): self
    {
        return $this->changed(new self($this->origin, $this->name, $access));
    }
}
