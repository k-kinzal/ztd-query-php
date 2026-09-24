<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Schema;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\SchemaInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a named PostgreSQL schema, optionally owned by another role and populated by nested commands.
 * @visibility public
 * @example Reading the schema and its elements
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app AUTHORIZATION alice CREATE TABLE t (id integer)');
 *     $statement->name // => 'app'
 *     $statement->owner->name // => 'alice'
 *     $statement->elements[0]->definition->table->name // => 't'
 * @example Rejecting elements of a schema that may already exist
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE SCHEMA IF NOT EXISTS app');
 *     $statement->withElements($binder->bind('CREATE SCHEMA app CREATE TABLE t (id integer)')->elements); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateSchemaStatement extends BoundStatement
{
    /**
     * @param list<BoundStatement> $elements Nested CREATE and GRANT commands; unqualified element names have an empty schema meaning the new schema
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly NamedRole|SessionRole|null $owner = null,
        public readonly bool $ifNotExists = false,
        public readonly array $elements = [],
    ) {
        DatabaseInvariant::target($origin, $name);
        SchemaInvariant::elements($origin, $ifNotExists, $elements);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the schema request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->owner, $this->ifNotExists, $this->elements);
    }

    /**
     * Replaces the schema name; elements are rebound inside the renamed schema.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->owner, $this->ifNotExists, $this->elements));
    }

    /**
     * Replaces or removes the owning role.
     */
    public function withOwner(NamedRole|SessionRole|null $owner): self
    {
        return $this->changed(new self($this->origin, $this->name, $owner, $this->ifNotExists, $this->elements));
    }

    /**
     * Selects whether an existing schema is accepted.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->owner, $ifNotExists, $this->elements));
    }

    /**
     * Replaces the nested commands.
     * @param list<BoundStatement> $elements Replacement elements
     */
    public function withElements(array $elements): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->owner, $this->ifNotExists, $elements));
    }
}
