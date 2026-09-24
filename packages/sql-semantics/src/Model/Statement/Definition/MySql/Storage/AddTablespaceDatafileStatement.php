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
 * Requests ALTER TABLESPACE that adds a data file to the tablespace, without touching the file.
 * @visibility public
 * @example Inspecting the added data file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER TABLESPACE ts ADD DATAFILE 'more.ibd' INITIAL_SIZE 64K");
 *     [$statement->name, $statement->datafile, $statement->changes->initialSize] // => ['ts', 'more.ibd', 65536]
 */
final class AddTablespaceDatafileStatement extends BoundStatement
{
    /**
     * The data file is named as written; sizes and options are applied with the change.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $datafile, public readonly TablespaceChanges $changes = new TablespaceChanges())
    {
        StorageInvariant::names($origin, $name);
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
        return new self($origin, $this->name, $this->datafile, $this->changes);
    }

    /**
     * Replaces the altered tablespace.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->datafile, $this->changes));
    }

    /**
     * Replaces the data file name.
     * @throws InvalidStructure
     */
    public function withDatafile(string $datafile): self
    {
        return $this->changed(new self($this->origin, $this->name, $datafile, $this->changes));
    }

    /**
     * Replaces the options applied with the change.
     * @throws InvalidStructure
     */
    public function withChanges(TablespaceChanges $changes): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->datafile, $changes));
    }
}
