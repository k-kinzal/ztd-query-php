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
 * Moves the default tablespace of a PostgreSQL database, including the WITH TABLESPACE spelling.
 * @visibility public
 * @example Reading the destination tablespace
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET TABLESPACE fast');
 *     $statement->tablespace // => 'fast'
 * @example Rejecting an empty tablespace
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET TABLESPACE fast');
 *     $statement->withTablespace(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetDatabaseTablespaceStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $tablespace)
    {
        DatabaseInvariant::target($origin, $name);
        DatabaseInvariant::target($origin, $tablespace);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the move while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->tablespace);
    }

    /**
     * Replaces the moved database.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->tablespace));
    }

    /**
     * Replaces the destination tablespace.
     */
    public function withTablespace(string $tablespace): self
    {
        return $this->changed(new self($this->origin, $this->name, $tablespace));
    }
}
