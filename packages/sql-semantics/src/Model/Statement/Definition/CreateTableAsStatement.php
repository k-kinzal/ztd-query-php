<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * A table whose columns are the result columns of an input query: the target name, optional column aliases, table
 * options, whether the rows are copied, and, on MySQL, what happens to a row whose unique key duplicates an earlier
 * row (fail, IGNORE, or REPLACE).
 *
 * @visibility public
 * @example Inspecting CreateTableAsStatement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE TABLE t AS SELECT 1 AS id');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\CreateTableAsStatement // => true
 * @example Reading the MySQL duplicate policy
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     $statement = $binder->bind('CREATE TABLE t IGNORE AS SELECT 1 AS id');
 *     $statement->duplicates // => \SqlSemantics\Model\Statement\Loading\DuplicateRows::Ignore
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TABLE `t` IGNORE AS SELECT 1 AS `id`'
 */
final class CreateTableAsStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @param list<string> $columns
     * @param \SqlSemantics\Model\Statement\Loading\DuplicateRows|null $duplicates MySQL IGNORE or REPLACE; null fails the statement on a duplicate key
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        public readonly array $columns = [],
        public readonly ?\SqlSemantics\Schema\Table\Properties $properties = null,
        public readonly bool $withData = true,
        public readonly bool $ifNotExists = false,
        public readonly ?\SqlSemantics\Model\Statement\Loading\DuplicateRows $duplicates = null,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\Collections::strings($columns);
        if ($duplicates !== null && $origin->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only MySQL decides between IGNORE and REPLACE for duplicate rows of a table filled from a query.');
        }
        if ($origin->dialect === \SqlSemantics\Dialect::MySql && \SqlSemantics\Model\Query\Locking\TableCreationLocks::exclusive($query)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('MySQL cannot fill a new table from a query that locks a stored table FOR UPDATE.');
        }
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
        return new static($origin, $this->name, $this->query, $this->columns, $this->properties, $this->withData, $this->ifNotExists, $this->duplicates);
    }

    /**
     * Replaces what happens to a row whose unique key duplicates an earlier row; null fails the statement.
     *
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withDuplicates(?\SqlSemantics\Model\Statement\Loading\DuplicateRows $duplicates): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->query, $this->columns, $this->properties, $this->withData, $this->ifNotExists, $duplicates));
    }
}
