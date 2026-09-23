<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a database and its initial defaults without creating it.
 * @visibility public
 * @example Inspecting the database identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE DATABASE app');
 *     $statement->name // => 'app'
 */
final class CreateDatabaseStatement extends BoundStatement
{
    /**
     * @param list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption> $options Ordered database default requests
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly array $options = [],
        public readonly bool $ifNotExists = false,
    ) {
        DatabaseInvariant::target($origin, $name);
        DatabaseInvariant::options($origin, $options, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the database request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options, $this->ifNotExists);
    }

    /**
     * Replaces the target in a separately validated statement.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options, $this->ifNotExists));
    }

    /**
     * Replaces the complete ordered default request.
     * @param list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption> $options Replacement default requests
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options, $this->ifNotExists));
    }

    /**
     * Changes the behavior when the database already exists.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->options, $ifNotExists));
    }
}
