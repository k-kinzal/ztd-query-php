<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Retrieval;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;

/**
 * PostgreSQL `SELECT ... INTO [TEMPORARY | UNLOGGED] [TABLE] name`: a new table is created from the query's columns and filled with its rows.
 * A temporary table lives in the session's temporary schema, so it is named without a schema or with `pg_temp`.
 *
 * @visibility public
 * @example Reading the created table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a INTO TEMP copied FROM t');
 *     [$statement->table->parts, $statement->persistence->value] // => [['copied'], 'temporary']
 */
final class SelectIntoTableStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly BoundQuery $query, public readonly QualifiedName $table, public readonly Persistence $persistence = Persistence::Permanent)
    {
        RetrievedQuery::check($origin, $query, Dialect::PostgreSql);
        if (count($table->parts) > 3) {
            throw new InvalidStructure('A table name has at most a database, a schema and a table part.');
        }
        $schema = $table->parts[count($table->parts) - 2] ?? null;
        if ($persistence === Persistence::Temporary && $schema !== null && preg_match('/^pg_temp(_\d+)?$/D', $schema) !== 1) {
            throw new InvalidStructure('A temporary table is created in the temporary schema, not in a named schema.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the fixed statement category.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Select;
    }

    /**
     * Retains the operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->query, $this->table, $this->persistence);
    }

    /**
     * Replaces the name and persistence of the created table.
     * @throws InvalidStructure
     */
    public function withTable(QualifiedName $table, Persistence $persistence): self
    {
        return $this->changed(new self($this->origin, $this->query, $table, $persistence));
    }

    /**
     * Replaces the query whose rows fill the table.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $query, $this->table, $this->persistence));
    }
}
