<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Database;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one PostgreSQL database, optionally terminating its sessions first with FORCE.
 * @visibility public
 * @example Reading the removal request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP DATABASE IF EXISTS app WITH (FORCE)');
 *     $statement->name // => 'app'
 *     $statement->ifExists // => true
 *     $statement->force // => true
 * @example Rejecting an empty database name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP DATABASE app');
 *     $statement->withName(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropDatabaseStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly bool $ifExists = false, public readonly bool $force = false)
    {
        DatabaseInvariant::target($origin, $name);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the removal while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists, $this->force);
    }

    /**
     * Replaces the removed database.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists, $this->force));
    }

    /**
     * Selects whether a missing database is ignored.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists, $this->force));
    }

    /**
     * Selects whether existing sessions are terminated before removal.
     */
    public function withForce(bool $force): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifExists, $force));
    }
}
