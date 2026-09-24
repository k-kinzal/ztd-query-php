<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Domain;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Creates a domain over a base type with an optional default, collation, and value constraints.
 * @visibility public
 * @example Creating a constrained domain
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN app.price AS numeric(10, 2) DEFAULT 0 NOT NULL CHECK (VALUE >= 0)');
 *     $statement->name->parts // => ['app', 'price']
 *     $statement->baseType->name // => 'numeric'
 *     count($statement->constraints) // => 2
 * @example Rejecting contradictory nullability
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer NOT NULL');
 *     $statement->withConstraints([new \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNotNull(), new \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNullable()]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateDomainStatement extends BoundStatement
{
    /**
     * @param list<Domain\DomainConstraint> $constraints
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly TypeDescriptor $baseType, public readonly ?Expression $default = null, public readonly ?QualifiedName $collation = null, public readonly array $constraints = [])
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::type($baseType);
        if ($collation !== null) {
            TypeSystemInvariant::name($collation);
        }
        Collections::objects($constraints, Domain\DomainConstraint::class);
        $nullability = array_unique(array_map(static fn (Domain\DomainConstraint $constraint): string => $constraint::class, array_filter($constraints, static fn (Domain\DomainConstraint $constraint): bool => !$constraint instanceof Domain\DomainCheck)));
        if (count($nullability) > 1) {
            throw new InvalidStructure('A domain cannot be declared both NULL and NOT NULL.');
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
        return new self($origin, $this->name, $this->baseType, $this->default, $this->collation, $this->constraints);
    }

    /**
     * Replaces the domain name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->baseType, $this->default, $this->collation, $this->constraints));
    }

    /**
     * Replaces the base type.
     */
    public function withBaseType(TypeDescriptor $baseType): self
    {
        return $this->changed(new self($this->origin, $this->name, $baseType, $this->default, $this->collation, $this->constraints));
    }

    /**
     * Replaces or removes the default expression.
     */
    public function withDefault(?Expression $default): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->baseType, $default, $this->collation, $this->constraints));
    }

    /**
     * Replaces or removes the collation.
     */
    public function withCollation(?QualifiedName $collation): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->baseType, $this->default, $collation, $this->constraints));
    }

    /**
     * Replaces the value constraints.
     * @param list<Domain\DomainConstraint> $constraints
     */
    public function withConstraints(array $constraints): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->baseType, $this->default, $this->collation, $constraints));
    }
}
