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
 * Requests CREATE UNDO TABLESPACE, available from MySQL 8.
 * @visibility public
 * @example Inspecting the undo data file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE UNDO TABLESPACE undo_3 ADD DATAFILE 'undo_3.ibu'");
 *     [$statement->name, $statement->datafile, $statement->engine] // => ['undo_3', 'undo_3.ibu', null]
 */
final class CreateUndoTablespaceStatement extends BoundStatement
{
    /**
     * The data file is required; the engine selection is optional.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $datafile, public readonly ?string $engine = null)
    {
        StorageInvariant::names($origin, $name, $engine);
        StorageInvariant::modern($origin, 'An undo tablespace');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->datafile, $this->engine);
    }

    /**
     * Replaces the undo tablespace name.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->datafile, $this->engine));
    }

    /**
     * Replaces the undo data file.
     * @throws InvalidStructure
     */
    public function withDatafile(string $datafile): self
    {
        return $this->changed(new self($this->origin, $this->name, $datafile, $this->engine));
    }

    /**
     * Replaces or removes the engine selection.
     * @throws InvalidStructure
     */
    public function withEngine(?string $engine): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->datafile, $engine));
    }
}
