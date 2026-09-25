<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use Override;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\IndexDeclaration;
use SqlSemantics\Model\Definition\TableDeclaration;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A MySQL table that declares its own columns and is filled from an input query: the declared columns, keys, table
 * options and partitioning come first, the query's result columns follow, and a row whose unique key duplicates an
 * earlier row either fails the statement, is skipped (IGNORE), or replaces the earlier row (REPLACE).
 * @visibility public
 * @example Reading the declared columns, the duplicate policy and the input query
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE u (a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TABLE t (id INT PRIMARY KEY) ENGINE = InnoDB IGNORE AS SELECT a FROM u');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement // => true
 *     $statement->definition->table->columns[0]->name // => 'id'
 *     $statement->definition->table->properties->engine // => 'InnoDB'
 *     $statement->duplicates // => \SqlSemantics\Model\Statement\Loading\DuplicateRows::Ignore
 *     $statement->query->resultColumns()[0]->name // => 'a'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TABLE `t`(`id` integer NOT NULL, PRIMARY KEY(`id`)) ENGINE `InnoDB` IGNORE AS SELECT `a` AS `a` FROM `u`'
 */
final class CreateTableFromQueryStatement extends BoundStatement
{
    /**
     * @param list<IndexDeclaration> $indexes Keys declared among the table elements
     * @param DuplicateRows|null $duplicates IGNORE or REPLACE; null fails the statement on a duplicate key
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableDeclaration $definition,
        public readonly BoundQuery $query,
        public readonly array $indexes = [],
        public readonly bool $ifNotExists = false,
        public readonly ?DuplicateRows $duplicates = null,
    ) {
        TableInvariant::dialect($origin);
        Collections::objects($indexes, IndexDeclaration::class);
        if ($definition->table->columns === []) {
            throw new InvalidStructure('A table filled from a query with its own declaration declares at least one column.');
        }
        foreach ($definition->table->columns as $column) {
            if ($column->type->dialect !== $origin->dialect) {
                throw new InvalidStructure('Declared columns must use the statement dialect.');
            }
        }
        if ($definition->table->properties !== null && !$definition->table->properties instanceof \SqlSemantics\Schema\Table\MySqlProperties) {
            throw new InvalidStructure('A MySQL table declaration carries MySQL table options.');
        }
        if (\SqlSemantics\Model\Query\Locking\TableCreationLocks::exclusive($query)) {
            throw new InvalidStructure('MySQL cannot fill a new table from a query that locks a stored table FOR UPDATE.');
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
        return new self($origin, $this->definition, $this->query, $this->indexes, $this->ifNotExists, $this->duplicates);
    }

    /**
     * Replaces the input query that fills the table.
     *
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $this->definition, $query, $this->indexes, $this->ifNotExists, $this->duplicates));
    }

    /**
     * Replaces what happens to a row whose unique key duplicates an earlier row; null fails the statement.
     *
     * @throws InvalidStructure
     */
    public function withDuplicates(?DuplicateRows $duplicates): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->query, $this->indexes, $this->ifNotExists, $duplicates));
    }

    /**
     * Replaces the declared table: its columns, keys, table options and partitioning.
     *
     * @param list<IndexDeclaration> $indexes
     * @throws InvalidStructure
     */
    public function withDefinition(TableDeclaration $definition, array $indexes = []): self
    {
        return $this->changed(new self($this->origin, $definition, $this->query, $indexes, $this->ifNotExists, $this->duplicates));
    }
}
