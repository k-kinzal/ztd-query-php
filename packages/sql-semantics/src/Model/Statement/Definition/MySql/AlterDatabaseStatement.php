<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes database defaults and access policy without applying those changes.
 * @visibility public
 * @example Inspecting the database identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
 *     $statement->name // => 'app'
 * @example Rejecting an alteration without any requested change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER DATABASE app READ ONLY 0');
 *     $statement->withOptions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterDatabaseStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> $options Ordered database default requests
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string|CurrentDatabase $name,
        public readonly array $options,
    ) {
        DatabaseInvariant::target($origin, $name);
        DatabaseInvariant::options($origin, $options, false);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the database request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the target in a separately validated statement.
     */
    public function withName(string|CurrentDatabase $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the complete ordered default request.
     * @param non-empty-list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> $options Replacement default requests
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
