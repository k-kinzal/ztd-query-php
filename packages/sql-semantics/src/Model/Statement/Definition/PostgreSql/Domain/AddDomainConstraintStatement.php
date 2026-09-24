<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Domain;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds a CHECK or NOT NULL constraint to a domain; a CHECK constraint may skip validating existing values.
 * @visibility public
 * @example Adding an unvalidated check
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN price ADD CONSTRAINT positive CHECK (VALUE > 0) NOT VALID');
 *     $statement->constraint->name // => 'positive'
 *     $statement->notValid // => true
 * @example Rejecting an unvalidated NOT NULL constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN price ADD NOT NULL');
 *     $statement->withNotValid(true); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddDomainConstraintStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly Domain\DomainNotNull|Domain\DomainCheck $constraint, public readonly bool $notValid = false)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($domain);
        if ($notValid && !$constraint instanceof Domain\DomainCheck) {
            throw new InvalidStructure('Only a domain CHECK constraint can skip validation.');
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
        return new self($origin, $this->domain, $this->constraint, $this->notValid);
    }

    /**
     * Replaces the altered domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->constraint, $this->notValid));
    }

    /**
     * Replaces the added constraint.
     */
    public function withConstraint(Domain\DomainNotNull|Domain\DomainCheck $constraint): self
    {
        return $this->changed(new self($this->origin, $this->domain, $constraint, $this->notValid));
    }

    /**
     * Chooses whether existing values are left unvalidated.
     */
    public function withNotValid(bool $notValid): self
    {
        return $this->changed(new self($this->origin, $this->domain, $this->constraint, $notValid));
    }
}
