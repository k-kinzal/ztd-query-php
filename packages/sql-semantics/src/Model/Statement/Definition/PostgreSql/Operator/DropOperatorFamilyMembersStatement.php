<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberRemoval;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes operators and support functions from an operator family.
 * @visibility public
 * @example Removing a support function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY app.ints USING btree DROP FUNCTION 1 (integer, bigint)');
 *     $statement->members[0]->number // => 1
 *     $statement->family->parts // => ['app', 'ints']
 */
final class DropOperatorFamilyMembersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<MemberRemoval> $members
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $family, public readonly string $method, public readonly array $members)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($family);
        TypeSystemInvariant::identifier($method);
        Collections::objects(Collections::nonEmpty($members), MemberRemoval::class);
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
     * Replaces the removed members.
     * @param non-empty-list<MemberRemoval> $members
     */
    public function withMembers(array $members): self
    {
        return $this->changed(new self($this->origin, $this->family, $this->method, $members));
    }
}
