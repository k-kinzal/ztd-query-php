<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\Table\Properties;

/**
 * PostgreSQL CREATE TABLE ... AS EXECUTE: a table whose columns and rows come from running a prepared query with
 * ordered arguments; it carries the target name, optional column aliases, table options, whether the rows are
 * copied, and IF NOT EXISTS.
 * @visibility public
 * @example Reading the target and the prepared query
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE TEMP TABLE copied (a) AS EXECUTE fetch_rows(1) WITH NO DATA', strict: false);
 *     $statement instanceof \SqlSemantics\Model\Statement\Prepared\CreateTableFromExecuteStatement // => true
 *     [$statement->name->parts, $statement->prepared, $statement->columns, $statement->withData] // => [['copied'], 'fetch_rows', ['a'], false]
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TEMPORARY TABLE "copied"("a") AS EXECUTE "fetch_rows"(1) WITH NO DATA'
 */
final class CreateTableFromExecuteStatement extends BoundStatement
{
    /**
     * @param string $prepared Name of the prepared query that fills the table
     * @param list<Expression> $arguments Arguments passed to the prepared query, in order
     * @param list<string> $columns Column aliases for the query's result columns
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly string $prepared,
        public readonly array $arguments = [],
        public readonly array $columns = [],
        public readonly ?Properties $properties = null,
        public readonly bool $withData = true,
        public readonly bool $ifNotExists = false,
    ) {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('CREATE TABLE ... AS EXECUTE requires PostgreSQL.');
        }
        if ($prepared === '') {
            throw new InvalidStructure('CREATE TABLE ... AS EXECUTE names its prepared query.');
        }
        if ($properties !== null && !$properties instanceof PostgreSqlProperties) {
            throw new InvalidStructure('A PostgreSQL table carries PostgreSQL table options.');
        }
        Collections::objects($arguments, Expression::class);
        Collections::strings($columns);
        foreach ($arguments as $argument) {
            if ($argument->type->dialect !== $origin->dialect) {
                throw new InvalidStructure('Prepared-query arguments must use the enclosing dialect.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->prepared, $this->arguments, $this->columns, $this->properties, $this->withData, $this->ifNotExists);
    }

    /**
     * Replaces the prepared query and its arguments.
     *
     * @param list<Expression> $arguments
     * @throws InvalidStructure
     */
    public function withPrepared(string $prepared, array $arguments = []): self
    {
        return $this->changed(new self($this->origin, $this->name, $prepared, $arguments, $this->columns, $this->properties, $this->withData, $this->ifNotExists));
    }
}
