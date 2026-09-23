<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\RepairOption;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\Maintenance\MySql\TableOperands;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Repairs the named tables using storage-engine repair strategies.
 * @visibility public
 * @example Inspecting the affected tables
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('REPAIR LOCAL TABLE t QUICK');
 *     $statement->tables[0]->declaration->name // => 't'
 */
final class RepairTablesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<TableReference> $tables Physical tables in request order
     * @param list<RepairOption> $options Requested checks in SQL order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $tables,
        public readonly array $options = [],
        public readonly BinlogPolicy $binlog = BinlogPolicy::Write,
    ) {
        TableOperands::validate($origin, $tables);
        Collections::objects($options, RepairOption::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Repair;
    }

    /**
     * Retains the operation's operands when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->tables, $this->options, $this->binlog);
    }

    /**
     * Changes the affected tables and resolves them against this request's schema.
     * @param non-empty-list<TableReference> $tables New physical targets
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->options, $this->binlog));
    }

    /**
     * Replaces the options selection in a newly validated request.
     * @param list<RepairOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->tables, $options, $this->binlog));
    }

    /**
     * Replaces the binlog selection in a newly validated request.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->tables, $this->options, $binlog));
    }

    /**
     * @return list<OutputColumn> Result roles and types, without runtime values
     */
    #[Override]
    public function resultColumns(): array
    {
        return ResultColumns::status($this->origin);
    }
}
