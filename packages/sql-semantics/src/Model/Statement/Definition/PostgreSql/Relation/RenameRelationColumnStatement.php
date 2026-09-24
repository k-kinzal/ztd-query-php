<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames one column of a table, view, materialized view, or foreign table.
 * @visibility public
 * @example Renaming a view column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b');
 *     $statement->relationKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::View
 *     [$statement->column, $statement->newName] // => ['a', 'b']
 * @example Rejecting a relation class without columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME a TO b');
 *     $statement->withRelationKind(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Sequence); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameRelationColumnStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly QualifiedName $name, public readonly string $column, public readonly string $newName, public readonly bool $ifExists = false, public readonly bool $only = false)
    {
        RelationInvariant::dialect($origin);
        RelationInvariant::kind($relationKind, [RelationKind::Table, RelationKind::View, RelationKind::MaterializedView, RelationKind::ForeignTable]);
        RelationInvariant::target($relationKind, $name, $only);
        CatalogInvariant::identifier($column);
        CatalogInvariant::identifier($newName);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Rename;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->relationKind, $this->name, $this->column, $this->newName, $this->ifExists, $this->only);
    }

    /**
     * Replaces the relation class within the classes that have columns.
     */
    public function withRelationKind(RelationKind $relationKind): self
    {
        return $this->changed(new self($this->origin, $relationKind, $this->name, $this->column, $this->newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the owning relation.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $name, $this->column, $this->newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the column's current name.
     */
    public function withColumn(string $column): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $column, $this->newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->column, $newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the tolerance for a missing relation.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->column, $this->newName, $ifExists, $this->only));
    }

    /**
     * Replaces whether descendant tables are excluded.
     */
    public function withOnly(bool $only): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->column, $this->newName, $this->ifExists, $only));
    }
}
