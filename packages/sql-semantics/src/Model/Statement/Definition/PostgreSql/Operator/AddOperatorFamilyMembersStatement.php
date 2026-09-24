<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorMember;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\SupportFunctionMember;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds operators and support functions to an operator family; every operator names its operand types.
 * @visibility public
 * @example Adding a cross-type operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY integer_ops USING btree ADD OPERATOR 1 < (integer, bigint)');
 *     $statement->members[0]->left->name // => 'integer'
 *     $statement->toString() // => 'ALTER OPERATOR FAMILY "integer_ops" USING "btree" ADD OPERATOR 1 < (integer, bigint)'
 */
final class AddOperatorFamilyMembersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<OperatorMember|SupportFunctionMember> $members
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $family, public readonly string $method, public readonly array $members)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($family);
        TypeSystemInvariant::identifier($method);
        Collections::alternatives(Collections::nonEmpty($members), [OperatorMember::class, SupportFunctionMember::class]);
        foreach ($members as $member) {
            if ($member instanceof OperatorMember && $member->left === null) {
                throw new InvalidStructure('An operator added to a family names its operand types.');
            }
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
        return new self($origin, $this->family, $this->method, $this->members);
    }

    /**
     * Replaces the altered family.
     */
    public function withFamily(QualifiedName $family): self
    {
        return $this->changed(new self($this->origin, $family, $this->method, $this->members));
    }

    /**
     * Replaces the index access method.
     */
    public function withMethod(string $method): self
    {
        return $this->changed(new self($this->origin, $this->family, $method, $this->members));
    }

    /**
     * Replaces the added members.
     * @param non-empty-list<OperatorMember|SupportFunctionMember> $members
     */
    public function withMembers(array $members): self
    {
        return $this->changed(new self($this->origin, $this->family, $this->method, $members));
    }
}
