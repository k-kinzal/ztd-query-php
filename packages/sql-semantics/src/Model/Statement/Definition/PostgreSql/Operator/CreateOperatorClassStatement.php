<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorSetMember;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\StorageMember;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Creates an operator class that lets an index access method index a type, optionally as the default class and within a named family.
 * @visibility public
 * @example Creating a default operator class
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS app.int_ops DEFAULT FOR TYPE integer USING btree FAMILY app.ints AS OPERATOR 1 <, FUNCTION 1 btint4cmp(integer, integer)');
 *     $statement->isDefault // => true
 *     $statement->family->parts // => ['app', 'ints']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE OPERATOR CLASS "app"."int_ops" DEFAULT FOR TYPE integer USING "btree" FAMILY "app"."ints" AS OPERATOR 1 <, FUNCTION 1 "btint4cmp"(integer, integer)'
 * @example Rejecting two storage types
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS box_ops FOR TYPE polygon USING gist AS STORAGE box');
 *     $statement->withMembers([$statement->members[0], $statement->members[0]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateOperatorClassStatement extends BoundStatement
{
    /**
     * @param non-empty-list<OperatorSetMember> $members
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly TypeDescriptor $type, public readonly string $method, public readonly array $members, public readonly bool $isDefault = false, public readonly ?QualifiedName $family = null)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::type($type);
        TypeSystemInvariant::identifier($method);
        Collections::objects(Collections::nonEmpty($members), OperatorSetMember::class);
        if (count(array_filter($members, static fn (OperatorSetMember $member): bool => $member instanceof StorageMember)) > 1) {
            throw new InvalidStructure('An operator class names at most one storage type.');
        }
        if ($family !== null) {
            TypeSystemInvariant::name($family);
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->type, $this->method, $this->members, $this->isDefault, $this->family);
    }

    /**
     * Replaces the class name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->type, $this->method, $this->members, $this->isDefault, $this->family));
    }

    /**
     * Replaces the indexed type.
     */
    public function withType(TypeDescriptor $type): self
    {
        return $this->changed(new self($this->origin, $this->name, $type, $this->method, $this->members, $this->isDefault, $this->family));
    }

    /**
     * Replaces the index access method.
     */
    public function withMethod(string $method): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $method, $this->members, $this->isDefault, $this->family));
    }

    /**
     * Replaces the members.
     * @param non-empty-list<OperatorSetMember> $members
     */
    public function withMembers(array $members): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->method, $members, $this->isDefault, $this->family));
    }

    /**
     * Chooses whether the class is the default for its type.
     */
    public function withIsDefault(bool $isDefault): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->method, $this->members, $isDefault, $this->family));
    }

    /**
     * Replaces or removes the operator family.
     */
    public function withFamily(?QualifiedName $family): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->method, $this->members, $this->isDefault, $family));
    }
}
