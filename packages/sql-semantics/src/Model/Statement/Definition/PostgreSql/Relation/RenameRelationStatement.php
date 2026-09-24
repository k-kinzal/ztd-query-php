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
 * Gives a table, sequence, view, materialized view, index, or foreign table a new name in its schema.
 * @visibility public
 * @example Renaming a sequence that may not exist
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old');
 *     $statement->relationKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Sequence
 *     $statement->ifExists // => true
 *     $statement->newName // => 's_old'
 * @example Rejecting ONLY on a sequence
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s RENAME TO t');
 *     $statement->withOnly(true); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameRelationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly QualifiedName $name, public readonly string $newName, public readonly bool $ifExists = false, public readonly bool $only = false)
    {
        RelationInvariant::dialect($origin);
        RelationInvariant::target($relationKind, $name, $only);
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
        return new self($origin, $this->relationKind, $this->name, $this->newName, $this->ifExists, $this->only);
    }

    /**
     * Replaces the renamed relation.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $name, $this->newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $newName, $this->ifExists, $this->only));
    }

    /**
     * Replaces the tolerance for a missing relation.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->newName, $ifExists, $this->only));
    }

    /**
     * Replaces whether descendant tables are excluded.
     */
    public function withOnly(bool $only): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->newName, $this->ifExists, $only));
    }
}
