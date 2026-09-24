<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Overrides planner cost or I/O concurrency parameters for relations stored in a PostgreSQL tablespace.
 * @visibility public
 * @example Reading the overridden parameters
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast SET (seq_page_cost = 0.5, effective_io_concurrency = 200)');
 *     $statement->parameters[1]->name->parts // => ['effective_io_concurrency']
 * @example Rejecting an empty override list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast SET (seq_page_cost = 0.5)');
 *     $statement->withParameters([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetTablespaceOptionsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<Parameter> $parameters Overrides in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $parameters)
    {
        DatabaseInvariant::target($origin, $name);
        TablespaceInvariant::parameters(Collections::nonEmpty($parameters));
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the overrides while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->parameters);
    }

    /**
     * Replaces the altered tablespace.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parameters));
    }

    /**
     * Replaces the overrides.
     * @param non-empty-list<Parameter> $parameters Replacement overrides
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->name, $parameters));
    }
}
