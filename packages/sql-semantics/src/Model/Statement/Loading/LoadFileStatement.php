<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;

/**
 * Inserts the rows of a text or XML file into a table (LOAD DATA or LOAD XML without ALGORITHM = BULK).
 * Without BULK the server ignores IN PRIMARY KEY ORDER, PARALLEL and MEMORY, so they are not retained.
 * @visibility public
 * @example Reading a row load
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, name TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("LOAD DATA LOCAL INFILE 'rows.csv' REPLACE INTO TABLE t (id, name) SET name = UPPER(name)");
 *     [$statement->format->value, $statement->local, $statement->duplicates?->value, $statement->file->text, count($statement->targets), count($statement->assignments)] // => ['DATA', true, 'REPLACE', "'rows.csv'", 2, 1]
 */
final class LoadFileStatement extends BoundStatement
{
    /**
     * @param LoadSource $location File, or an S3 location read as a file; URL requires ALGORITHM = BULK
     * @param list<Expression> $targets Columns and user variables receiving the input fields in order; empty uses every table column
     * @param list<Assignment> $assignments SET items computing columns from the targets
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly LoadFormat $format,
        public readonly Literal $file,
        public readonly TableReference $table,
        public readonly ?LoadScheduling $scheduling = null,
        public readonly bool $local = false,
        public readonly LoadSource $location = LoadSource::File,
        public readonly ?DuplicateRows $duplicates = null,
        public readonly ?NamedPartitions $partitions = null,
        public readonly LoadLayout $layout = new LoadLayout(),
        public readonly array $targets = [],
        public readonly array $assignments = [],
    ) {
        LoadTargets::source($origin, $location, $file);
        if ($location === LoadSource::Url) {
            throw new InvalidStructure('Loading from a URL requires ALGORITHM = BULK.');
        }
        LoadTargets::fields($targets);
        LoadTargets::assignments($assignments);
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
        return new self($origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments);
    }

    /**
     * Reads the input as delimited text or as XML.
     */
    public function withFormat(LoadFormat $format): self
    {
        return $this->changed(new self($this->origin, $format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Reads another file.
     */
    public function withFile(Literal $file): self
    {
        return $this->changed(new self($this->origin, $this->format, $file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Loads into another table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Requests or omits CONCURRENT or LOW_PRIORITY.
     */
    public function withScheduling(?LoadScheduling $scheduling): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Reads the file on the client (LOCAL) or on the server.
     */
    public function withLocal(bool $local): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Names the file as a file or an S3 location.
     */
    public function withLocation(LoadSource $location): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Replaces or skips duplicate rows, or reports them when null.
     */
    public function withDuplicates(?DuplicateRows $duplicates): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $duplicates, $this->partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Restricts or stops restricting the load to named partitions.
     */
    public function withPartitions(?NamedPartitions $partitions): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $partitions, $this->layout, $this->targets, $this->assignments));
    }

    /**
     * Reads the input with another character set, separators or skipped rows.
     */
    public function withLayout(LoadLayout $layout): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $layout, $this->targets, $this->assignments));
    }

    /**
     * Sends the input fields to other columns and user variables.
     * @param list<Expression> $targets
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $targets, $this->assignments));
    }

    /**
     * Replaces the SET items.
     * @param list<Assignment> $assignments
     */
    public function withAssignments(array $assignments): self
    {
        return $this->changed(new self($this->origin, $this->format, $this->file, $this->table, $this->scheduling, $this->local, $this->location, $this->duplicates, $this->partitions, $this->layout, $this->targets, $assignments));
    }
}
