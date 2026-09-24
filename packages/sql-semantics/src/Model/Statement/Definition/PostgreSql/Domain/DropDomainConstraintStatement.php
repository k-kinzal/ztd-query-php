<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Domain;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one named constraint of a domain.
 * @visibility public
 * @example Dropping a domain constraint when it exists
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN price DROP CONSTRAINT IF EXISTS positive CASCADE');
 *     $statement->constraint // => 'positive'
 *     $statement->ifExists // => true
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class DropDomainConstraintStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly string $constraint, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($domain);
        TypeSystemInvariant::identifier($constraint);
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
        return new self($origin, $this->domain, $this->constraint, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the altered domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->constraint, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the dropped constraint name.
     */
    public function withConstraint(string $constraint): self
    {
        return $this->changed(new self($this->origin, $this->domain, $constraint, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for a missing constraint.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->domain, $this->constraint, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->domain, $this->constraint, $this->ifExists, $behavior));
    }
}
