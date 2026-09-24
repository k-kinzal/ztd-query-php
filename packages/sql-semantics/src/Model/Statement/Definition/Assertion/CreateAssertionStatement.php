<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Assertion;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;

/**
 * Declares a database-wide condition in the SQL-standard form that the PostgreSQL grammar accepts; the server does not implement it.
 * @visibility public
 * @example Reading the asserted condition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE ASSERTION positive CHECK (NOT EXISTS (SELECT 1 FROM t WHERE a < 0)) DEFERRABLE');
 *     $statement->name->parts // => ['positive']
 *     $statement->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableImmediate
 * @example Rejecting an overqualified assertion name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
 *     $statement->withName(new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c', 'd'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateAssertionStatement extends BoundStatement
{
    /**
     * @param Expression $condition Condition every database state must satisfy (CHECK)
     * @param CheckingTime $checking When the condition is checked (DEFERRABLE, INITIALLY DEFERRED)
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly Expression $condition, public readonly CheckingTime $checking)
    {
        if ($origin->dialect !== Dialect::PostgreSql || $condition->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('CREATE ASSERTION is a PostgreSQL grammar form with a PostgreSQL condition.');
        }
        CatalogInvariant::name($name, 3);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the assertion while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->condition, $this->checking);
    }

    /**
     * Replaces the assertion name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->condition, $this->checking));
    }

    /**
     * Replaces the asserted condition.
     */
    public function withCondition(Expression $condition): self
    {
        return $this->changed(new self($this->origin, $this->name, $condition, $this->checking));
    }

    /**
     * Replaces when the condition is checked.
     */
    public function withChecking(CheckingTime $checking): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->condition, $checking));
    }
}
