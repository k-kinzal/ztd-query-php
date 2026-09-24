<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Moves every table, index, or materialized view of one tablespace, optionally only those of given owners, into another tablespace.
 * @visibility public
 * @example Moving the indexes of two owners without waiting for locks
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT');
 *     $statement->relationKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Index
 *     [$statement->tablespace, $statement->newTablespace, $statement->nowait, count($statement->owners)] // => ['slow', 'fast', true, 2]
 * @example Rejecting a relation class that cannot move as a group
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLE ALL IN TABLESPACE a SET TABLESPACE b');
 *     $statement->withRelationKind(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Sequence); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class MoveTablespaceRelationsStatement extends BoundStatement
{
    /**
     * @param list<NamedRole|SessionRole> $owners Owners whose relations move; empty selects every relation
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly string $tablespace, public readonly string $newTablespace, public readonly array $owners = [], public readonly bool $nowait = false)
    {
        RelationInvariant::dialect($origin);
        RelationInvariant::kind($relationKind, [RelationKind::Table, RelationKind::Index, RelationKind::MaterializedView]);
        CatalogInvariant::identifier($tablespace);
        CatalogInvariant::identifier($newTablespace);
        Collections::alternatives($owners, [NamedRole::class, SessionRole::class]);
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
        return new self($origin, $this->relationKind, $this->tablespace, $this->newTablespace, $this->owners, $this->nowait);
    }

    /**
     * Replaces the relation class within tables, indexes, and materialized views.
     */
    public function withRelationKind(RelationKind $relationKind): self
    {
        return $this->changed(new self($this->origin, $relationKind, $this->tablespace, $this->newTablespace, $this->owners, $this->nowait));
    }

    /**
     * Replaces the source tablespace.
     */
    public function withTablespace(string $tablespace): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $tablespace, $this->newTablespace, $this->owners, $this->nowait));
    }

    /**
     * Replaces the destination tablespace.
     */
    public function withNewTablespace(string $newTablespace): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->tablespace, $newTablespace, $this->owners, $this->nowait));
    }

    /**
     * Replaces the owner selection; an empty list selects every relation.
     * @param list<NamedRole|SessionRole> $owners
     */
    public function withOwners(array $owners): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->tablespace, $this->newTablespace, $owners, $this->nowait));
    }

    /**
     * Replaces whether the move fails instead of waiting for locks.
     */
    public function withNowait(bool $nowait): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->tablespace, $this->newTablespace, $this->owners, $nowait));
    }
}
