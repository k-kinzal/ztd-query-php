<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops exactly one PostgreSQL index concurrently, without cascading.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP INDEX CONCURRENTLY IF EXISTS ix', strict: false);
 *     $statement->toString() // => 'DROP INDEX CONCURRENTLY IF EXISTS "ix"'
 *
 * @visibility public
 */
final class DropIndexConcurrentlyStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Concurrent index deletion requires PostgreSQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the drop operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->ifExists);
    }
}
