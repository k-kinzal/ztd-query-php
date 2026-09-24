<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Cast;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CastIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops the cast between a source and a target type.
 * @visibility public
 * @example Dropping a cast when it exists
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP CAST IF EXISTS (bigint AS money) CASCADE');
 *     $statement->cast->target->name // => 'money'
 *     $statement->ifExists // => true
 *     $statement->toString() // => 'DROP CAST IF EXISTS(bigint AS "money") CASCADE'
 */
final class DropCastStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly CastIdentity $cast, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::dialect($origin);
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
        return new self($origin, $this->cast, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the dropped cast.
     */
    public function withCast(CastIdentity $cast): self
    {
        return $this->changed(new self($this->origin, $cast, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for a missing cast.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->cast, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->cast, $this->ifExists, $behavior));
    }
}
