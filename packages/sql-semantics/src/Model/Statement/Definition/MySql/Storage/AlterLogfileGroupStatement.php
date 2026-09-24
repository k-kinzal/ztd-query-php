<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests ALTER LOGFILE GROUP ... ADD, which adds one log file to the group.
 * @visibility public
 * @example Inspecting the added log file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'more.log' INITIAL_SIZE 4K ENGINE NDB");
 *     [$statement->file, $statement->initialSize, $statement->engine] // => ['more.log', 4096, 'NDB']
 */
final class AlterLogfileGroupStatement extends BoundStatement
{
    /**
     * A redo log file exists only in MySQL 5.x grammars; the initial size counts bytes.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly LogFileKind $fileKind, public readonly string $file, public readonly ?int $initialSize = null, public readonly ?string $engine = null, public readonly CompletionWait $waiting = CompletionWait::Wait)
    {
        StorageInvariant::names($origin, $name, $engine);
        StorageInvariant::quantities($initialSize);
        if ($fileKind === LogFileKind::Redo) {
            StorageInvariant::older($origin, 'A redo log file');
        }
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
        return new self($origin, $this->name, $this->fileKind, $this->file, $this->initialSize, $this->engine, $this->waiting);
    }

    /**
     * Replaces the altered logfile group.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->fileKind, $this->file, $this->initialSize, $this->engine, $this->waiting));
    }

    /**
     * Replaces the added log file and its kind.
     * @throws InvalidStructure
     */
    public function withFile(LogFileKind $fileKind, string $file): self
    {
        return $this->changed(new self($this->origin, $this->name, $fileKind, $file, $this->initialSize, $this->engine, $this->waiting));
    }

    /**
     * Replaces or removes the initial size of the added file.
     * @throws InvalidStructure
     */
    public function withInitialSize(?int $initialSize): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->fileKind, $this->file, $initialSize, $this->engine, $this->waiting));
    }

    /**
     * Replaces or removes the engine selection.
     * @throws InvalidStructure
     */
    public function withEngine(?string $engine): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->fileKind, $this->file, $this->initialSize, $engine, $this->waiting));
    }

    /**
     * Replaces the completion-wait request.
     * @throws InvalidStructure
     */
    public function withWaiting(CompletionWait $waiting): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->fileKind, $this->file, $this->initialSize, $this->engine, $waiting));
    }
}
