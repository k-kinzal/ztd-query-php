<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
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
 * Checks physical tables using explicitly selected storage-engine checks.
 * @visibility public
 * @example Inspecting the affected tables
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CHECK TABLE t QUICK');
 *     $statement->tables[0]->declaration->name // => 't'
 * @example Rejecting an empty selection
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CHECK TABLE t');
 *     new \SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CheckTablesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<TableReference> $tables Physical tables in request order
     * @param list<CheckOption> $options Requested checks in SQL order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $tables,
        public readonly array $options = [],
    ) {
        TableOperands::validate($origin, $tables);
        Collections::objects($options, CheckOption::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Check;
    }

    /**
     * Retains the operation's operands when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->tables, $this->options);
    }

    /**
     * Changes the affected tables and resolves them against this request's schema.
     * @param non-empty-list<TableReference> $tables New physical targets
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->options));
    }

    /**
     * Replaces the options selection in a newly validated request.
     * @param list<CheckOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->tables, $options));
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
