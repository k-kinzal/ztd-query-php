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
 * Records the current collation version of a PostgreSQL database's default collation.
 * @visibility public
 * @example Reading the refreshed database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app REFRESH COLLATION VERSION');
 *     $statement->name // => 'app'
 * @example Rejecting an empty database name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app REFRESH COLLATION VERSION');
 *     $statement->withName(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RefreshDatabaseCollationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name)
    {
        DatabaseInvariant::target($origin, $name);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the refresh while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name);
    }

    /**
     * Replaces the refreshed database.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name));
    }
}
