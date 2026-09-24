<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Schema;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Database\PostgreSql\SchemaInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a PostgreSQL schema named after and owned by a role, optionally populated by nested commands.
 * @visibility public
 * @example Reading the owning role
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA AUTHORIZATION CURRENT_USER');
 *     $statement->owner // => \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser
 * @example Rejecting elements of an owner schema that may already exist
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE SCHEMA IF NOT EXISTS AUTHORIZATION alice');
 *     $statement->withElements($binder->bind('CREATE SCHEMA AUTHORIZATION alice CREATE TABLE t (id integer)')->elements); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateAuthorizationSchemaStatement extends BoundStatement
{
    /**
     * @param list<BoundStatement> $elements Nested CREATE and GRANT commands; unqualified element names have an empty schema meaning the new schema
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole $owner,
        public readonly bool $ifNotExists = false,
        public readonly array $elements = [],
    ) {
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
        return new self($origin, $this->owner, $this->ifNotExists, $this->elements);
    }

    /**
     * Replaces the owning role, which also names the schema.
     */
    public function withOwner(NamedRole|SessionRole $owner): self
    {
        return $this->changed(new self($this->origin, $owner, $this->ifNotExists, $this->elements));
    }

    /**
     * Selects whether an existing schema is accepted.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->owner, $ifNotExists, $this->elements));
    }

    /**
     * Replaces the nested commands.
     * @param list<BoundStatement> $elements Replacement elements
     */
    public function withElements(array $elements): self
    {
        return $this->changed(new self($this->origin, $this->owner, $this->ifNotExists, $elements));
    }
}
