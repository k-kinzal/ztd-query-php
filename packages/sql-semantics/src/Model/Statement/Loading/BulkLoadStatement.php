<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads delimited text into a table with the bulk loader (LOAD DATA ... ALGORITHM = BULK).
 * The bulk loader reads server, URL or S3 sources, maps fields to every column in order, and takes an exclusive table lock, so LOCAL, column lists, SET, IGNORE, LINES STARTING BY and lock scheduling do not apply.
 * @visibility public
 * @example Reading a bulk load
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT PRIMARY KEY)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("LOAD DATA FROM S3 's3://bucket/t.' COUNT 4 IN PRIMARY KEY ORDER INTO TABLE t PARALLEL = 8 MEMORY = 1G ALGORITHM = BULK");
 *     [$statement->location->value, $statement->fileCount, $statement->keyOrdered, $statement->parallel, $statement->memory] // => ['S3', 4, true, 8, '1073741824']
 */
final class BulkLoadStatement extends BoundStatement
{
    /**
     * @param int|null $fileCount Number of files sharing the name prefix; null reads one file
     * @param bool $keyOrdered Whether the input is sorted by primary key (IN PRIMARY KEY ORDER)
     * @param Literal|null $compression Compression algorithm name of the input files
     * @param int|null $parallel Requested concurrency; null uses the server default
     * @param string|null $memory Memory budget in bytes as decimal digits; null uses the server default
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly LoadSource $location,
        public readonly Literal $file,
        public readonly TableReference $table,
        public readonly ?int $fileCount = null,
        public readonly bool $keyOrdered = false,
        public readonly ?DuplicateRows $duplicates = null,
        public readonly ?NamedPartitions $partitions = null,
        public readonly LoadLayout $layout = new LoadLayout(),
        public readonly ?Literal $compression = null,
        public readonly ?int $parallel = null,
        public readonly ?string $memory = null,
    ) {
        LoadTargets::source($origin, $location, $file);
        ReplicationRelease::require($origin, 'ALGORITHM = BULK', 80000);
        if ($parallel !== null || $memory !== null) {
            ReplicationRelease::require($origin, 'PARALLEL and MEMORY', 80200);
        }
        if ($compression !== null) {
            ReplicationRelease::require($origin, 'COMPRESSION', 80400);
        }
        if (($fileCount !== null && $fileCount < 1) || ($parallel !== null && $parallel < 0) || ($memory !== null && preg_match('/^(0|[1-9][0-9]*)$/D', $memory) !== 1)) {
            throw new InvalidStructure('A bulk load requires a positive file count, a nonnegative concurrency and a decimal memory size.');
        }
        if ($compression !== null && ($compression->type->dialect !== $origin->dialect || $compression->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('A compression algorithm is named by a text literal.');
        }
        if ($duplicates === DuplicateRows::Ignore || $layout->lines->start !== null || ($layout->fields->terminator !== null && strlen(SeparatorText::bytes($layout->fields->terminator)) > 1)) {
            throw new InvalidStructure('A bulk load cannot ignore duplicates, skip a line prefix, or use a multi-byte field terminator.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Load;
    }

    /**
     * Retains the load while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory);
    }

    /**
     * Reads the input from another kind of source.
     */
    public function withLocation(LoadSource $location): self
    {
        return $this->changed(new self($this->origin, $location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Reads another file or file name prefix.
     */
    public function withFile(Literal $file): self
    {
        return $this->changed(new self($this->origin, $this->location, $file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Loads into another table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Reads another number of files, or one file when null.
     */
    public function withFileCount(?int $fileCount): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Declares or stops declaring the input sorted by primary key.
     */
    public function withKeyOrdered(bool $keyOrdered): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Replaces duplicate rows, or reports them when null.
     */
    public function withDuplicates(?DuplicateRows $duplicates): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Restricts or stops restricting the load to named partitions.
     */
    public function withPartitions(?NamedPartitions $partitions): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $partitions, $this->layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Reads the input with another character set, separators or skipped rows.
     */
    public function withLayout(LoadLayout $layout): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $layout, $this->compression, $this->parallel, $this->memory));
    }

    /**
     * Names or removes the compression algorithm of the input.
     */
    public function withCompression(?Literal $compression): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $compression, $this->parallel, $this->memory));
    }

    /**
     * Requests another concurrency, or the default when null.
     */
    public function withParallel(?int $parallel): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $parallel, $this->memory));
    }

    /**
     * Requests another memory budget in bytes, or the default when null.
     * @param string|null $memory Decimal digits
     */
    public function withMemory(?string $memory): self
    {
        return $this->changed(new self($this->origin, $this->location, $this->file, $this->table, $this->fileCount, $this->keyOrdered, $this->duplicates, $this->partitions, $this->layout, $this->compression, $this->parallel, $memory));
    }
}
