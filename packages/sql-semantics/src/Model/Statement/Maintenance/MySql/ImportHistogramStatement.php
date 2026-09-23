<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Dialect;
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
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Imports a serialized histogram without interpreting its runtime JSON value.
 * @visibility public
 * @example Inspecting the histogram target
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id USING DATA \'{}\'');
 *     $statement->table->declaration->name // => 't'
 */
final class ImportHistogramStatement extends BoundStatement implements ResultStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly ColumnReference|UnresolvedColumnReference $column,
        public readonly Literal $data,
        public readonly BinlogPolicy $binlog = BinlogPolicy::Write,
    ) {
        TableOperands::validate($origin, [$table]);
        ColumnOperands::validate($table, [$column]);
        if ($data->type->dialect !== Dialect::MySql || $data->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('Imported histogram data requires a MySQL text literal.');
        }
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
        return new self($origin, $this->table, $this->column, $this->data, $this->binlog);
    }

    /**
     * Changes the table and its column selection together, retaining their shared identity.
     */
    public function withTarget(TableReference $table, ColumnReference|UnresolvedColumnReference $column): self
    {
        return $this->changed(new self($this->origin, $table, $column, $this->data, $this->binlog));
    }

    /**
     * Replaces the data request while preserving the other operands.
     */
    public function withData(Literal $data): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->column, $data, $this->binlog));
    }

    /**
     * Replaces the binlog request while preserving the other operands.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->column, $this->data, $binlog));
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
