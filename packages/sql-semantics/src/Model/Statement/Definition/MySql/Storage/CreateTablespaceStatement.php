<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests CREATE TABLESPACE without creating any file.
 * @visibility public
 * @example Inspecting the first data file and logfile group
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'ts.dat' USE LOGFILE GROUP lg ENGINE NDB");
 *     [$statement->name, $statement->datafile, $statement->logfileGroup] // => ['ts', 'ts.dat', 'lg']
 */
final class CreateTablespaceStatement extends BoundStatement
{
    /**
     * A MySQL 5.x tablespace requires its first data file; MySQL 8 lets the engine name it.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ?string $datafile = null, public readonly ?string $logfileGroup = null, public readonly TablespaceOptions $options = new TablespaceOptions())
    {
        StorageInvariant::names($origin, $name, $logfileGroup);
        if ($datafile === null) {
            StorageInvariant::modern($origin, 'A tablespace without an explicit data file');
        }
        StorageInvariant::options($origin, $options);
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
        return new self($origin, $this->name, $this->datafile, $this->logfileGroup, $this->options);
    }

    /**
     * Replaces the tablespace name.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->datafile, $this->logfileGroup, $this->options));
    }

    /**
     * Replaces or removes the explicit first data file.
     * @throws InvalidStructure
     */
    public function withDatafile(?string $datafile): self
    {
        return $this->changed(new self($this->origin, $this->name, $datafile, $this->logfileGroup, $this->options));
    }

    /**
     * Replaces or removes the NDB logfile group the tablespace uses.
     * @throws InvalidStructure
     */
    public function withLogfileGroup(?string $logfileGroup): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->datafile, $logfileGroup, $this->options));
    }

    /**
     * Replaces the initial tablespace properties.
     * @throws InvalidStructure
     */
    public function withOptions(TablespaceOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->datafile, $this->logfileGroup, $options));
    }
}
