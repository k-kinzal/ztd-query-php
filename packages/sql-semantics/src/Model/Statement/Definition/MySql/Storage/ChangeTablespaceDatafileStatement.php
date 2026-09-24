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
 * Requests the MySQL 5.x ALTER TABLESPACE ... CHANGE DATAFILE form that resizes one data file.
 * @visibility public
 * @example Inspecting the resized data file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("ALTER TABLESPACE ts CHANGE DATAFILE 'ts.dat' MAX_SIZE 1G");
 *     [$statement->datafile, $statement->maxSize] // => ['ts.dat', 1073741824]
 */
final class ChangeTablespaceDatafileStatement extends BoundStatement
{
    /**
     * Requires at least one size in bytes; available only in MySQL 5.x grammars.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $datafile, public readonly ?int $initialSize = null, public readonly ?int $autoextendSize = null, public readonly ?int $maxSize = null)
    {
        StorageInvariant::names($origin, $name);
        StorageInvariant::quantities($initialSize, $autoextendSize, $maxSize);
        StorageInvariant::older($origin, 'CHANGE DATAFILE');
        if ($initialSize === null && $autoextendSize === null && $maxSize === null) {
            throw new InvalidStructure('CHANGE DATAFILE requires at least one size.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the resize request while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->datafile, $this->initialSize, $this->autoextendSize, $this->maxSize);
    }

    /**
     * Replaces the resized data file.
     * @throws InvalidStructure
     */
    public function withDatafile(string $datafile): self
    {
        return $this->changed(new self($this->origin, $this->name, $datafile, $this->initialSize, $this->autoextendSize, $this->maxSize));
    }

    /**
     * Replaces the requested sizes in bytes; at least one remains required.
     * @throws InvalidStructure
     */
    public function withSizes(?int $initialSize, ?int $autoextendSize, ?int $maxSize): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->datafile, $initialSize, $autoextendSize, $maxSize));
    }
}
