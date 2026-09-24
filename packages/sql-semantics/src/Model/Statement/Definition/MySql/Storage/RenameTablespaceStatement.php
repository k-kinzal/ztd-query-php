<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests ALTER TABLESPACE ... RENAME TO, available from MySQL 8.
 * @visibility public
 * @example Inspecting both tablespace names
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER TABLESPACE old_space RENAME TO new_space');
 *     [$statement->name, $statement->newName] // => ['old_space', 'new_space']
 */
final class RenameTablespaceStatement extends BoundStatement
{
    /**
     * Both names are required and nonempty.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $newName)
    {
        StorageInvariant::names($origin, $name, $newName);
        StorageInvariant::modern($origin, 'Renaming a tablespace');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains both names while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->newName);
    }

    /**
     * Replaces the renamed tablespace.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->newName));
    }

    /**
     * Replaces the name the tablespace receives.
     * @throws InvalidStructure
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->name, $newName));
    }
}
