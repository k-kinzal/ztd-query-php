<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\FlushedTables;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Flushes the named tables and locks them so their files can be copied (FLUSH TABLES ... FOR EXPORT).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t FOR EXPORT');
 *     $statement->tables[0]->declaration->name // => 't'
 */
final class FlushTablesForExportStatement extends BoundStatement
{
    /**
     * @var list<TableReference>
     */
    public readonly array $tables;

    /**
     * @param list<TableReference> $tables Physical tables in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $tables, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        $this->tables = FlushedTables::check($origin, $tables, 'FLUSH TABLES FOR EXPORT', true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Flush;
    }

    /**
     * Retains the tables and binary log policy when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->tables, $this->binlog);
    }

    /**
     * Replaces the flushed tables.
     * @param list<TableReference> $tables
     */
    public function withTables(array $tables): self
    {
        return $this->changed(new self($this->origin, $tables, $this->binlog));
    }

    /**
     * Chooses whether the request is written to the binary log.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->tables, $binlog));
    }
}
