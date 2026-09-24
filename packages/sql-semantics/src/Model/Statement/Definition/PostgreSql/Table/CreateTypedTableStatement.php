<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\TableConstraint;

/**
 * Declares a typed table whose columns are the attributes of a composite type; the declaration may override those columns and add constraints.
 * @visibility public
 * @example Reading the type and the overrides
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE TABLE people OF app.person (name WITH OPTIONS NOT NULL) TABLESPACE fast');
 *     $statement->type->parts // => ['app', 'person']
 *     $statement->columns[0]->column // => 'name'
 *     $statement->properties->tablespace // => 'fast'
 * @example Rejecting a type name with too many components
 *     $origin = new \SqlSemantics\Model\Statement\Origin('s0', new \SqlParser\Parser\Node('stmt', 0, []), \SqlSemantics\Dialect::PostgreSql);
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreateTypedTableStatement($origin, new \SqlSemantics\Model\Relation\QualifiedName(['t']), new \SqlSemantics\Model\Relation\QualifiedName(['d', 's', 'ty'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateTypedTableStatement extends BoundStatement
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
        public readonly QualifiedName $type,
        public readonly array $columns = [],
        public readonly array $constraints = [],
        public readonly array $exclusions = [],
        public readonly PostgreSqlProperties $properties = new PostgreSqlProperties(),
        public readonly bool $ifNotExists = false,
    ) {
        TableFormInvariant::check($origin, $columns, $constraints, $properties);
        Collections::objects($exclusions, ExclusionConstraint::class);
        CatalogInvariant::name($name, 3);
        CatalogInvariant::name($type, 2);
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
        return new self($origin, $this->name, $this->type, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists);
    }

    /**
     * Replaces the declared table name.
     *
     * @throws InvalidStructure
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->type, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the composite type that supplies the columns.
     *
     * @throws InvalidStructure
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $this->name, $type, $this->columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the column overrides.
     *
     * @param list<PartitionColumn> $columns
     * @throws InvalidStructure
     */
    public function withColumns(array $columns): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $columns, $this->constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the added table constraints.
     *
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public function withConstraints(array $constraints): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->columns, $constraints, $this->exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces the EXCLUDE constraints.
     *
     * @param list<ExclusionConstraint> $exclusions
     * @throws InvalidStructure
     */
    public function withExclusions(array $exclusions): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->columns, $this->constraints, $exclusions, $this->properties, $this->ifNotExists));
    }

    /**
     * Replaces persistence, partitioning, and storage properties.
     *
     * @throws InvalidStructure
     */
    public function withProperties(PostgreSqlProperties $properties): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->columns, $this->constraints, $this->exclusions, $properties, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing table.
     *
     * @throws InvalidStructure
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->columns, $this->constraints, $this->exclusions, $this->properties, $ifNotExists));
    }
}
