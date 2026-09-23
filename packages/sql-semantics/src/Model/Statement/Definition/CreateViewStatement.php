<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a named query whose result columns are read like a table.
 *
 * @visibility public
 * @example Inspecting the declared query
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE VIEW v(a) AS SELECT 1');
 *     [$statement->replace, $statement->columns, count($statement->query->outputs)] // => [true, ['a'], 1]
 */
final class CreateViewStatement extends BoundStatement
{
    /**
     * @param list<string> $columns Declared result names; PostgreSQL recursive views require them
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly BoundQuery $query,
        public readonly array $columns = [],
        public readonly bool $temporary = false,
        public readonly bool $replace = false,
        public readonly bool $ifNotExists = false,
        public readonly ViewCheck $check = ViewCheck::None,
        public readonly MySqlViewProperties|PostgreSqlViewProperties|null $properties = null,
    ) {
        Collections::strings($columns);
        if ($properties instanceof MySqlViewProperties && $origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL view properties require a MySQL view.');
        }
        if ($properties instanceof PostgreSqlViewProperties && $origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('PostgreSQL view properties require a PostgreSQL view.');
        }
        if ($properties instanceof PostgreSqlViewProperties && $properties->recursive && $columns === []) {
            throw new InvalidStructure('A recursive view requires its declared result names.');
        }
        if ($ifNotExists && $origin->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('Only SQLite views have an existence policy.');
        }
        if ($replace && $origin->dialect === Dialect::Sqlite) {
            throw new InvalidStructure('SQLite views cannot be replaced by a declaration.');
        }
        if ($check !== ViewCheck::None && $origin->dialect === Dialect::Sqlite) {
            throw new InvalidStructure('SQLite views have no check option.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->query, $this->columns, $this->temporary, $this->replace, $this->ifNotExists, $this->check, $this->properties);
    }

    /**
     * Replaces the declared query and revalidates the declaration against the schema.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $this->name, $query, $this->columns, $this->temporary, $this->replace, $this->ifNotExists, $this->check, $this->properties));
    }

    /**
     * Renames the declared view without changing its query or options.
     * @throws InvalidStructure
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->query, $this->columns, $this->temporary, $this->replace, $this->ifNotExists, $this->check, $this->properties));
    }
}
