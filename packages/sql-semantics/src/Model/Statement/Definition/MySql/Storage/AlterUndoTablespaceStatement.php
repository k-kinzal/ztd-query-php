<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Definition\Storage\UndoTablespaceState;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests ALTER UNDO TABLESPACE ... SET ACTIVE or INACTIVE, available from MySQL 8.
 * @visibility public
 * @example Inspecting the requested undo state
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER UNDO TABLESPACE undo_3 SET INACTIVE');
 *     [$statement->name, $statement->state->value] // => ['undo_3', 'INACTIVE']
 */
final class AlterUndoTablespaceStatement extends BoundStatement
{
    /**
     * The state is required; the engine selection is optional.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly UndoTablespaceState $state, public readonly ?string $engine = null)
    {
        StorageInvariant::names($origin, $name, $engine);
        StorageInvariant::modern($origin, 'An undo tablespace');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the state request while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->state, $this->engine);
    }

    /**
     * Replaces the altered undo tablespace.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->state, $this->engine));
    }

    /**
     * Replaces the requested state.
     * @throws InvalidStructure
     */
    public function withState(UndoTablespaceState $state): self
    {
        return $this->changed(new self($this->origin, $this->name, $state, $this->engine));
    }

    /**
     * Replaces or removes the engine selection.
     * @throws InvalidStructure
     */
    public function withEngine(?string $engine): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->state, $engine));
    }
}
