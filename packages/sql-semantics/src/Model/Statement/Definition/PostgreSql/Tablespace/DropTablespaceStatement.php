<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one empty PostgreSQL tablespace.
 * @visibility public
 * @example Reading the removed tablespace
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP TABLESPACE IF EXISTS fast');
 *     $statement->name // => 'fast'
 *     $statement->ifExists // => true
 * @example Rejecting an empty tablespace name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP TABLESPACE fast');
 *     $statement->withName(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropTablespaceStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly bool $ifExists = false)
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
        return new self($origin, $this->name, $this->ifExists);
    }

    /**
     * Replaces the removed tablespace.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists));
    }

    /**
     * Selects whether a missing tablespace is ignored.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists));
    }
}
