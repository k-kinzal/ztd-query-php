<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyEndpoint;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes the rows of a table to a server file, a server program or the client; binding writes no data.
 * @visibility public
 * @example Reading the table and destination
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('COPY BINARY t TO STDOUT');
 *     [$statement->options->format->value, $statement->toString()] // => ['binary', 'COPY "public"."t" TO STDOUT WITH (FORMAT \'binary\')']
 */
final class CopyToStatement extends BoundStatement
{
    /**
     * @param TableReference $table Resolved or diagnosed source table
     * @param list<string> $columns Written columns in written order; empty writes every column
     * @param CopyEndpoint $destination Server file, server program or client output
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly array $columns,
        public readonly CopyEndpoint $destination,
        public readonly CopyOptions $options = new CopyOptions(),
    ) {
        CopyTable::validate($origin, $table, $columns, $options);
        CopyOptionRules::writing($options);
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
        return new self($origin, $this->table, $this->columns, $this->destination, $this->options);
    }

    /**
     * Writes another table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->columns, $this->destination, $this->options));
    }

    /**
     * Replaces the written columns; an empty list writes every column.
     * @param list<string> $columns
     */
    public function withColumns(array $columns): self
    {
        return $this->changed(new self($this->origin, $this->table, $columns, $this->destination, $this->options));
    }

    /**
     * Writes to another destination.
     */
    public function withDestination(CopyEndpoint $destination): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $destination, $this->options));
    }

    /**
     * Replaces the options.
     */
    public function withOptions(CopyOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->columns, $this->destination, $options));
    }
}
