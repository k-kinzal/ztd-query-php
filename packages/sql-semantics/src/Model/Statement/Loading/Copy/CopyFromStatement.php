<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyEndpoint;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Loads rows into a table from a server file, a server program or the client; binding reads no data.
 * @visibility public
 * @example Reading the table, source and filter
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("COPY t (a) FROM STDIN WITH (FORMAT csv) WHERE a > 0");
 *     [$statement->table->declaration->name, $statement->columns, $statement->where !== null] // => ['t', ['a'], true]
 * @example Rejecting FORCE_QUOTE when reading
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     (new \SqlSemantics\Binder($schema))->bind('COPY t FROM STDIN CSV FORCE QUOTE *'); // throws \SqlSemantics\InvalidSql
 */
final class CopyFromStatement extends BoundStatement
{
    /**
     * @param TableReference $table Resolved or diagnosed target table
     * @param list<string> $columns Loaded columns in written order; empty loads every column
     * @param CopyEndpoint $input Server file, server program or client input
     * @param Expression|null $where Row filter evaluated for each loaded row; null loads every row
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly array $columns,
        public readonly CopyEndpoint $input,
        public readonly CopyOptions $options = new CopyOptions(),
        public readonly ?Expression $where = null,
    ) {
        CopyTable::validate($origin, $table, $columns, $options);
        CopyOptionRules::reading($options);
        if ($where !== null) {
            StatementOperands::expressions([$where], $origin->dialect);
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Copy;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->columns, $this->input, $this->options, $this->where);
    }

    /**
     * Loads another table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->columns, $this->input, $this->options, $this->where));
    }

    /**
     * Replaces the loaded columns; an empty list loads every column.
     * @param list<string> $columns
     */
    public function withColumns(array $columns): self
    {
        return $this->changed(new self($this->origin, $this->table, $columns, $this->input, $this->options, $this->where));
    }

    /**
     * Reads from another source.
     */
    public function withInput(CopyEndpoint $input): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $input, $this->options, $this->where));
    }

    /**
     * Replaces the options.
     */
    public function withOptions(CopyOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $this->input, $options, $this->where));
    }

    /**
     * Replaces the row filter; null loads every row.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $this->input, $this->options, $where));
    }
}
