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
 * Adds (SET NOT NULL) or removes (DROP NOT NULL) the NOT NULL constraint of a domain.
 * @visibility public
 * @example Requiring domain values
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN price SET NOT NULL');
 *     $statement->notNull // => true
 *     $statement->withNotNull(false)->toString() // => 'ALTER DOMAIN "price" DROP NOT NULL'
 */
final class AlterDomainNullabilityStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly bool $notNull)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($domain);
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
        return new self($origin, $this->domain, $this->notNull);
    }

    /**
     * Replaces the altered domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->notNull));
    }

    /**
     * Chooses between SET NOT NULL and DROP NOT NULL.
     */
    public function withNotNull(bool $notNull): self
    {
        return $this->changed(new self($this->origin, $this->domain, $notNull));
    }
}
