<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\Histogram\BucketCount;
use SqlSemantics\Model\Maintenance\Histogram\ColumnOperands;
use SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\Maintenance\MySql\TableOperands;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Samples the selected columns to build histogram statistics.
 * @visibility public
 * @example Inspecting the histogram target
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id WITH 10 BUCKETS');
 *     $statement->table->declaration->name // => 't'
 * @example Rejecting an empty selection
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id');
 *     new \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement($statement->origin, $statement->table, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class UpdateHistogramStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<ColumnReference|UnresolvedColumnReference> $columns Columns to maintain
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly array $columns,
        public readonly ?BucketCount $buckets = null,
        public readonly RefreshPolicy $refresh = RefreshPolicy::Default,
        public readonly BinlogPolicy $binlog = BinlogPolicy::Write,
    ) {
        TableOperands::validate($origin, [$table]);
        ColumnOperands::validate($table, $columns);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Analyze;
    }

    /**
     * Retains all histogram operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->columns, $this->buckets, $this->refresh, $this->binlog);
    }

    /**
     * Changes the table and its column selection together, retaining their shared identity.
     * @param non-empty-list<ColumnReference|UnresolvedColumnReference> $columns
     */
    public function withTarget(TableReference $table, array $columns): self
    {
        return $this->changed(new self($this->origin, $table, $columns, $this->buckets, $this->refresh, $this->binlog));
    }

    /**
     * Replaces the buckets request while preserving the other operands.
     */
    public function withBuckets(?BucketCount $buckets): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $buckets, $this->refresh, $this->binlog));
    }

    /**
     * Replaces the refresh request while preserving the other operands.
     */
    public function withRefresh(RefreshPolicy $refresh): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $this->buckets, $refresh, $this->binlog));
    }

    /**
     * Replaces the binlog request while preserving the other operands.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $this->buckets, $this->refresh, $binlog));
    }

    /**
     * @return list<OutputColumn> Status roles produced by the request
     */
    #[Override]
    public function resultColumns(): array
    {
        return ResultColumns::status($this->origin);
    }
}
