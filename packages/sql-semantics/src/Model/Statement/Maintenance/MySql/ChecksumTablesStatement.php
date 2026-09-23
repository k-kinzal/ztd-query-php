<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\MySql\ChecksumMode;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\Maintenance\MySql\TableOperands;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests one checksum per named table without reading table values.
 * @visibility public
 * @example Inspecting the affected tables
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CHECKSUM TABLE t QUICK');
 *     $statement->tables[0]->declaration->name // => 't'
 */
final class ChecksumTablesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<TableReference> $tables Physical tables in request order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $tables,
        public readonly ChecksumMode $mode = ChecksumMode::Automatic,
    ) {
        TableOperands::validate($origin, $tables);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Checksum;
    }

    /**
     * Retains the operation's operands when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->tables, $this->mode);
    }

    /**
     * Changes the affected tables and resolves them against this request's schema.
     * @param non-empty-list<TableReference> $tables New physical targets
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->mode));
    }

    /**
     * Replaces the mode selection in a newly validated request.
     */
    public function withMode(ChecksumMode $mode): self
    {
        return $this->changed(new self($this->origin, $this->tables, $mode));
    }

    /**
     * @return list<OutputColumn> Result roles and types, without runtime values
     */
    #[Override]
    public function resultColumns(): array
    {
        return ResultColumns::checksum($this->origin);
    }
}
