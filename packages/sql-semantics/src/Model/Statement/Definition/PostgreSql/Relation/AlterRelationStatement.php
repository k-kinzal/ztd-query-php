<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Definition\Relation\Partition\AttachIndexPartition;
use SqlSemantics\Model\Definition\Relation\Partition\AttachPartition;
use SqlSemantics\Model\Definition\Relation\Partition\DetachPartition;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Applies an ordered list of typed actions to a table, index, sequence, view, materialized view, or foreign table.
 * Partition attachment and detachment stand alone; every other action can be combined.
 * @visibility public
 * @example Reading combined actions on a table that may not exist
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey');
 *     $statement->relationKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Table
 *     [$statement->ifExists, $statement->only, count($statement->actions)] // => [true, true, 2]
 * @example Rejecting an empty action list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET LOGGED');
 *     $statement->withActions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterRelationStatement extends BoundStatement
{
    /**
     * @param non-empty-list<RelationAction> $actions
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationKind $relationKind, public readonly QualifiedName $name, public readonly array $actions, public readonly bool $ifExists = false, public readonly bool $only = false)
    {
        RelationInvariant::dialect($origin);
        RelationInvariant::target($relationKind, $name, $only);
        Collections::objects(Collections::nonEmpty($actions), RelationAction::class);
        $partition = array_filter($actions, static fn (RelationAction $action): bool => $action instanceof AttachPartition || $action instanceof DetachPartition || $action instanceof AttachIndexPartition);
        if ($partition !== [] && count($actions) !== 1) {
            throw new InvalidStructure('A partition action is the only action of its statement.');
        }
        foreach ($partition as $action) {
            RelationInvariant::kind($relationKind, $action instanceof AttachIndexPartition ? [RelationKind::Index] : [RelationKind::Table]);
        }
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
        return new self($origin, $this->relationKind, $this->name, $this->actions, $this->ifExists, $this->only);
    }

    /**
     * Replaces the altered relation.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $name, $this->actions, $this->ifExists, $this->only));
    }

    /**
     * Replaces the ordered actions in a separately validated statement.
     * @param non-empty-list<RelationAction> $actions
     */
    public function withActions(array $actions): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $actions, $this->ifExists, $this->only));
    }

    /**
     * Replaces the tolerance for a missing relation.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->actions, $ifExists, $this->only));
    }

    /**
     * Replaces whether descendant tables are excluded.
     */
    public function withOnly(bool $only): self
    {
        return $this->changed(new self($this->origin, $this->relationKind, $this->name, $this->actions, $this->ifExists, $only));
    }
}
