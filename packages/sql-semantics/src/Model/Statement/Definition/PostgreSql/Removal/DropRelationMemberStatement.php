<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one policy or rule of a table; triggers use DropTableTriggerStatement.
 * @visibility public
 * @example Dropping a policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE');
 *     $statement->memberKind // => \SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind::Policy
 *     $statement->table->parts // => ['app', 'docs']
 * @example Rejecting a member class with its own removal statement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP RULE r ON t');
 *     $statement->withMemberKind(\SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind::Trigger); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropRelationMemberStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RelationMemberKind $memberKind, public readonly string $name, public readonly QualifiedName $table, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        if (!in_array($memberKind, [RelationMemberKind::Policy, RelationMemberKind::Rule], true)) {
            throw new InvalidStructure('Only policies and rules are removed by this form.');
        }
        RemovalInvariant::dialect($origin);
        CatalogInvariant::identifier($name);
        CatalogInvariant::name($table, 3);
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
        return new self($origin, $this->memberKind, $this->name, $this->table, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the member class within policies and rules.
     */
    public function withMemberKind(RelationMemberKind $memberKind): self
    {
        return $this->changed(new self($this->origin, $memberKind, $this->name, $this->table, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the removed member's name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $this->memberKind, $name, $this->table, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the owning table.
     */
    public function withTable(QualifiedName $table): self
    {
        return $this->changed(new self($this->origin, $this->memberKind, $this->name, $table, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for a missing member.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->memberKind, $this->name, $this->table, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->memberKind, $this->name, $this->table, $this->ifExists, $behavior));
    }
}
