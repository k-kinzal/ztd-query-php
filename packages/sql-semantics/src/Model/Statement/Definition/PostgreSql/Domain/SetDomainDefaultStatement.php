<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Domain;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets the default value of a domain.
 * @visibility public
 * @example Setting a domain default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.price SET DEFAULT 1');
 *     $statement->domain->parts // => ['app', 'price']
 *     $statement->toString() // => 'ALTER DOMAIN "app"."price" SET DEFAULT 1'
 */
final class SetDomainDefaultStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly Expression $default)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($domain);
        if ($default->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A domain default requires a PostgreSQL expression.');
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
        return new self($origin, $this->domain, $this->default);
    }

    /**
     * Replaces the altered domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->default));
    }

    /**
     * Replaces the default expression.
     */
    public function withDefault(Expression $default): self
    {
        return $this->changed(new self($this->origin, $this->domain, $default));
    }
}
