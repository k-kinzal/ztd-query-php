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
 * Moves a table, sequence, view, materialized view, or foreign table into another schema.
 * @visibility public
 * @example Moving a table that may not exist
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive');
 *     $statement->schema // => 'archive'
 *     $statement->ifExists // => true
 * @example Rejecting an index, which cannot change schema independently of its table
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLE t SET SCHEMA s');
 *     $statement->withRelationKind(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Index); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetRelationSchemaStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly QualifiedName $name, public readonly string $schema, public readonly bool $ifExists = false, public readonly bool $only = false)
    {
        RelationInvariant::dialect($origin);
        RelationInvariant::kind($relationKind, [RelationKind::Table, RelationKind::Sequence, RelationKind::View, RelationKind::MaterializedView, RelationKind::ForeignTable]);
        RelationInvariant::target($relationKind, $name, $only);
        CatalogInvariant::identifier($schema);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->relationKind, $this->name, $this->schema, $this->ifExists, $this->only);
    }

    /**
     * Replaces the relation class within the classes that can move.
     */
    public function withRelationKind(RelationKind $relationKind): self
    {
        return $this->changed(new self($this->origin, $relationKind, $this->name, $this->schema, $this->ifExists, $this->only));
    }

    /**
     * Replaces the moved relation.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $name, $this->schema, $this->ifExists, $this->only));
    }

    /**
     * Replaces the destination schema.
     */
    public function withSchema(string $schema): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $schema, $this->ifExists, $this->only));
    }

    /**
     * Replaces the tolerance for a missing relation.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->schema, $ifExists, $this->only));
    }

    /**
     * Replaces whether descendant tables are excluded.
     */
    public function withOnly(bool $only): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->schema, $this->ifExists, $only));
    }
}
