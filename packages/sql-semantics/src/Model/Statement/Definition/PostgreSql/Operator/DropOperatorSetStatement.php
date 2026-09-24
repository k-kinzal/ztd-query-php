<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\OperatorSetIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one operator class or operator family of an index access method.
 * @visibility public
 * @example Dropping an operator family
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP OPERATOR FAMILY IF EXISTS app.ints USING btree CASCADE');
 *     $statement->object->kind // => \SqlSemantics\Model\Definition\Catalog\Kind\OperatorSetKind::OperatorFamily
 *     $statement->object->method // => 'btree'
 *     $statement->toString() // => 'DROP OPERATOR FAMILY IF EXISTS "app"."ints" USING "btree" CASCADE'
 */
final class DropOperatorSetStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly OperatorSetIdentity $object, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
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
        return new self($origin, $this->object, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the dropped operator class or family.
     */
    public function withObject(OperatorSetIdentity $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for a missing object.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->object, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->object, $this->ifExists, $behavior));
    }
}
