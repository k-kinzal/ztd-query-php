<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant as Names;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames one constraint of a domain.
 * @visibility public
 * @example Reading the constraint identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative');
 *     $statement->constraint // => 'positive'
 *     $statement->newName // => 'non_negative'
 */
final class RenameDomainConstraintStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $domain, public readonly string $constraint, public readonly string $newName)
    {
        CatalogInvariant::dialect($origin);
        Names::name($domain, 2);
        Names::identifier($constraint);
        Names::identifier($newName);
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
        return new self($origin, $this->domain, $this->constraint, $this->newName);
    }

    /**
     * Replaces the owning domain.
     */
    public function withDomain(QualifiedName $domain): self
    {
        return $this->changed(new self($this->origin, $domain, $this->constraint, $this->newName));
    }

    /**
     * Replaces the constraint's current name.
     */
    public function withConstraint(string $constraint): self
    {
        return $this->changed(new self($this->origin, $this->domain, $constraint, $this->newName));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->domain, $this->constraint, $newName));
    }
}
