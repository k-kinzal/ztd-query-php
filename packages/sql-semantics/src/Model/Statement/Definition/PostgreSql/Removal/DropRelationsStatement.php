<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops sequences or foreign tables; tables, views, indexes, and materialized views have their own removal forms.
 * @visibility public
 * @example Dropping sequences
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE');
 *     $statement->kind->value // => 'DROP'
 *     $statement->relationKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Sequence
 *     count($statement->names) // => 2
 * @example Rejecting a relation class with its own removal form
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE s');
 *     $statement->withRelationKind(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Table); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropRelationsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly array $names, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        if (!in_array($relationKind, [RelationKind::Sequence, RelationKind::ForeignTable], true)) {
            throw new InvalidStructure('Tables, views, indexes, and materialized views are removed by their own statement forms.');
        }
        RemovalInvariant::names($origin, $names, 3);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->relationKind, $this->names, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the relation class within the two classes this form removes.
     */
    public function withRelationKind(RelationKind $relationKind): self
    {
        return $this->changed(new self($this->origin, $relationKind, $this->names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the removed relations.
     * @param non-empty-list<QualifiedName> $names
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for missing relations.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->names, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->names, $this->ifExists, $behavior));
    }
}
