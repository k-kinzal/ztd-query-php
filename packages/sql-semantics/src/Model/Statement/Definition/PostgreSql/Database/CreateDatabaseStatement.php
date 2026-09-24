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
 * Requests a new PostgreSQL database with the listed properties; unlisted properties come from the template.
 * @visibility public
 * @example Reading the requested database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE DATABASE app OWNER alice TEMPLATE template0 LOCALE 'C'");
 *     $statement->name // => 'app'
 *     $statement->options[1]->value // => 'template0'
 * @example Rejecting a repeated property
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app');
 *     $owner = new \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption(\SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::Owner, 'alice');
 *     $statement->withOptions([$owner, $owner]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateDatabaseStatement extends BoundStatement
{
    /**
     * @param list<DatabaseOption> $options Requested properties in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $options = [])
    {
        DatabaseInvariant::target($origin, $name);
        DatabaseInvariant::options($options, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the requested database while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the name of the database to create.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the requested properties.
     * @param list<DatabaseOption> $options Replacement properties
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
