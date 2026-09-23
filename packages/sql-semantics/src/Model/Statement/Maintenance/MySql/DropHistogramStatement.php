<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\Histogram\ColumnOperands;
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
 * Requests removal of histogram statistics for selected table columns.
 * @visibility public
 * @example Inspecting the histogram target
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
 *     $statement->table->declaration->name // => 't'
 */
final class DropHistogramStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<ColumnReference|UnresolvedColumnReference> $columns Columns to maintain
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly array $columns,
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
        return new self($origin, $this->table, $this->columns, $this->binlog);
    }

    /**
     * Changes the table and its column selection together, retaining their shared identity.
     * @param non-empty-list<ColumnReference|UnresolvedColumnReference> $columns
     */
    public function withTarget(TableReference $table, array $columns): self
    {
        return $this->changed(new self($this->origin, $table, $columns, $this->binlog));
    }

    /**
     * Replaces the binlog request while preserving the other operands.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $binlog));
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
