<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops operators, each selected by its symbol and operand types.
 * @visibility public
 * @example Dropping a prefix and a binary operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP OPERATOR IF EXISTS app.~ (NONE, text), === (integer, integer) CASCADE');
 *     $statement->operators[0]->left // => null
 *     $statement->operators[1]->name->parts // => ['===']
 *     $statement->toString() // => 'DROP OPERATOR IF EXISTS "app".~ (NONE, text), === (integer, integer) CASCADE'
 * @example Rejecting an empty operator list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP OPERATOR === (integer, integer)');
 *     $statement->withOperators([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropOperatorsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<OperatorIdentity> $operators
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $operators, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($operators), OperatorIdentity::class);
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
        return new self($origin, $this->operators, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the dropped operators.
     * @param non-empty-list<OperatorIdentity> $operators
     */
    public function withOperators(array $operators): self
    {
        return $this->changed(new self($this->origin, $operators, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for missing operators.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->operators, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->operators, $this->ifExists, $behavior));
    }
}
