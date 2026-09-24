<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Domain;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates existing values against a domain constraint that was added NOT VALID.
 * @visibility public
 * @example Validating a domain constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN price VALIDATE CONSTRAINT positive');
 *     $statement->constraint // => 'positive'
 */
final class ValidateDomainConstraintStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly string $constraint)
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
        return new self($origin, $this->domain, $this->constraint);
    }

    /**
     * Replaces the altered domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->constraint));
    }

    /**
     * Replaces the validated constraint name.
     */
    public function withConstraint(string $constraint): self
    {
        return $this->changed(new self($this->origin, $this->domain, $constraint));
    }
}
