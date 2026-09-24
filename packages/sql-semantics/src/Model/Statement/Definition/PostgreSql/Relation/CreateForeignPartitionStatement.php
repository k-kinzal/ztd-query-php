<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound;
use SqlSemantics\Model\Definition\Relation\Partition\HashPartitionBound;
use SqlSemantics\Model\Definition\Relation\Partition\ListPartitionBound;
use SqlSemantics\Model\Definition\Relation\Partition\RangePartitionBound;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\TableConstraint;

/**
 * Declares a foreign table as a partition of a partitioned table, inheriting its columns and covering one bound.
 * @visibility public
 * @example Reading the partition declaration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE app.p(a INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF app.p (CHECK (a > 0)) FOR VALUES IN (1, 2) SERVER remote');
 *     $statement->parent->parts // => ['app', 'p']
 *     count($statement->bound->values) // => 2
 *     count($statement->constraints) // => 1
 */
final class CreateForeignPartitionStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options
     * @param list<PartitionColumn> $columns
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly QualifiedName $parent,
        public readonly HashPartitionBound|ListPartitionBound|RangePartitionBound|DefaultPartitionBound $bound,
        public readonly string $server,
        public readonly array $options = [],
        public readonly array $columns = [],
        public readonly array $constraints = [],
        public readonly bool $ifNotExists = false,
    ) {
        RelationInvariant::dialect($origin);
        CatalogInvariant::name($name, 3);
        CatalogInvariant::name($parent, 3);
        CatalogInvariant::identifier($server);
        Collections::objects($options, ForeignOption::class);
        Collections::objects($columns, PartitionColumn::class);
        Collections::objects($constraints, TableConstraint::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->parent, $this->bound, $this->server, $this->options, $this->columns, $this->constraints, $this->ifNotExists);
    }

    /**
     * Replaces the declared partition name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parent, $this->bound, $this->server, $this->options, $this->columns, $this->constraints, $this->ifNotExists));
    }

    /**
     * Replaces the partitioned parent.
     */
    public function withParent(QualifiedName $parent): self
    {
        return $this->changed(new self($this->origin, $this->name, $parent, $this->bound, $this->server, $this->options, $this->columns, $this->constraints, $this->ifNotExists));
    }

    /**
     * Replaces the covered bound.
     */
    public function withBound(HashPartitionBound|ListPartitionBound|RangePartitionBound|DefaultPartitionBound $bound): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $bound, $this->server, $this->options, $this->columns, $this->constraints, $this->ifNotExists));
    }

    /**
     * Replaces the foreign server.
     */
    public function withServer(string $server): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $server, $this->options, $this->columns, $this->constraints, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing table.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $this->server, $this->options, $this->columns, $this->constraints, $ifNotExists));
    }
}
