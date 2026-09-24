<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests CREATE LOGFILE GROUP with its first log file, without creating the file.
 * @visibility public
 * @example Inspecting the first log file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'undo.log' INITIAL_SIZE 16M");
 *     [$statement->name, $statement->fileKind->value, $statement->file, $statement->options->initialSize] // => ['lg', 'UNDOFILE', 'undo.log', 16777216]
 */
final class CreateLogfileGroupStatement extends BoundStatement
{
    /**
     * A redo log file exists only in MySQL 5.x grammars.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly LogFileKind $fileKind, public readonly string $file, public readonly LogfileGroupOptions $options = new LogfileGroupOptions())
    {
        StorageInvariant::names($origin, $name);
        if ($fileKind === LogFileKind::Redo) {
            StorageInvariant::older($origin, 'A redo log file');
        }
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
        return new self($origin, $this->name, $this->fileKind, $this->file, $this->options);
    }

    /**
     * Replaces the logfile group name.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->fileKind, $this->file, $this->options));
    }

    /**
     * Replaces the first log file and its kind.
     * @throws InvalidStructure
     */
    public function withFile(LogFileKind $fileKind, string $file): self
    {
        return $this->changed(new self($this->origin, $this->name, $fileKind, $file, $this->options));
    }

    /**
     * Replaces the initial logfile group properties.
     * @throws InvalidStructure
     */
    public function withOptions(LogfileGroupOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->fileKind, $this->file, $options));
    }
}
