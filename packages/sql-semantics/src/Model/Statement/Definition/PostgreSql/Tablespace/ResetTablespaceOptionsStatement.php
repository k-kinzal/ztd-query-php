<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes parameter overrides of a PostgreSQL tablespace; names that are not set are ignored by the server.
 * @visibility public
 * @example Reading the removed parameters
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast RESET (seq_page_cost, random_page_cost)');
 *     $statement->names[1]->parts // => ['random_page_cost']
 * @example Rejecting an empty removal list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast RESET (seq_page_cost)');
 *     $statement->withNames([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ResetTablespaceOptionsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<QualifiedName> $names Removed parameters in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $names)
    {
        DatabaseInvariant::target($origin, $name);
        TablespaceInvariant::names($names);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the removals while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->names);
    }

    /**
     * Replaces the altered tablespace.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->names));
    }

    /**
     * Replaces the removed parameter names.
     * @param non-empty-list<QualifiedName> $names Replacement names
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $this->name, $names));
    }
}
