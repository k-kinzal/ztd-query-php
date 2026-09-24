<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint;
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
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\TableConstraint;

/**
 * Declares a table as a partition of a partitioned table: it takes the parent's columns, covers one bound, and may override columns, add constraints, and be partitioned itself.
 * @visibility public
 * @example Reading the parent, bound, and overrides
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, at DATE) PARTITION BY RANGE (at)'));
 *     $statement = $binder->bind('CREATE TABLE p2024 PARTITION OF p (id WITH OPTIONS NOT NULL, CHECK (id > 0)) FOR VALUES FROM (\'2024-01-01\') TO (\'2025-01-01\')');
 *     $statement->parent->parts // => ['p']
 *     $statement->bound instanceof \SqlSemantics\Model\Definition\Relation\Partition\RangePartitionBound // => true
 *     $statement->columns[0]->nullability // => \SqlSemantics\Type\Nullability::NotNull
 *     count($statement->constraints) // => 1
 * @example Rejecting a partition that declares INHERITS
 *     $origin = new \SqlSemantics\Model\Statement\Origin('s0', new \SqlParser\Parser\Node('stmt', 0, []), \SqlSemantics\Dialect::PostgreSql);
 *     $bound = new \SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound();
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreatePartitionStatement($origin, new \SqlSemantics\Model\Relation\QualifiedName(['c']), new \SqlSemantics\Model\Relation\QualifiedName(['p']), $bound, properties: new \SqlSemantics\Schema\Table\PostgreSqlProperties(parents: [new \SqlSemantics\Model\Relation\QualifiedName(['a'])])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreatePartitionStatement extends BoundStatement
{
    /**
     * @param list<PartitionColumn> $columns
     * @param list<TableConstraint> $constraints
     * @param list<ExclusionConstraint> $exclusions
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly QualifiedName $parent,
        public readonly HashPartitionBound|ListPartitionBound|RangePartitionBound|DefaultPartitionBound $bound,
        public readonly array $columns = [],
        public readonly array $constraints = [],
        public readonly array $exclusions = [],
        public readonly PostgreSqlProperties $properties = new PostgreSqlProperties(),
        public readonly bool $ifNotExists = false,
    ) {
        TableFormInvariant::check($origin, $columns, $constraints, $properties);
        Collections::objects($exclusions, ExclusionConstraint::class);
        CatalogInvariant::name($name, 3);
        CatalogInvariant::name($parent, 3);
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
        return new self($origin, $this->name, $this->parent, $this->bound, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists);
    }

    /**
     * Replaces the declared partition name.
     *
     * @throws InvalidStructure
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parent, $this->bound, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the partitioned parent.
     *
     * @throws InvalidStructure
     */
    public function withParent(QualifiedName $parent): self
    {
        return $this->changed(new self($this->origin, $this->name, $parent, $this->bound, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the covered bound.
     *
     * @throws InvalidStructure
     */
    public function withBound(HashPartitionBound|ListPartitionBound|RangePartitionBound|DefaultPartitionBound $bound): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $bound, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the column overrides.
     *
     * @param list<PartitionColumn> $columns
     * @throws InvalidStructure
     */
    public function withColumns(array $columns): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the added table constraints.
     *
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public function withConstraints(array $constraints): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $this->columns, $constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the EXCLUDE constraints.
     *
     * @param list<ExclusionConstraint> $exclusions
     * @throws InvalidStructure
     */
    public function withExclusions(array $exclusions): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $this->columns, $this->constraints, $exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces persistence, sub-partitioning, and storage properties.
     *
     * @throws InvalidStructure
     */
    public function withProperties(PostgreSqlProperties $properties): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $this->columns, $this->constraints, $this->exclusions, $properties, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing table.
     *
     * @throws InvalidStructure
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parent, $this->bound, $this->columns, $this->constraints, $this->exclusions, $this->properties, $ifNotExists));
    }
}
