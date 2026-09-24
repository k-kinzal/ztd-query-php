<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Database;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the connection policy or template flag of an existing PostgreSQL database; an empty list only checks access.
 * @visibility public
 * @example Reading the changed properties
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app WITH ALLOW_CONNECTIONS false IS_TEMPLATE true');
 *     $statement->options[0]->value // => false
 * @example Rejecting a property fixed at creation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app CONNECTION LIMIT 3');
 *     $statement->withOptions([new \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption(\SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::Owner, 'alice')]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterDatabaseOptionsStatement extends BoundStatement
{
    /**
     * @param list<DatabaseOption> $options Changed properties in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $options)
    {
        DatabaseInvariant::target($origin, $name);
        DatabaseInvariant::options($options, false);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the property changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the altered database.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the changed properties.
     * @param list<DatabaseOption> $options Replacement properties
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
