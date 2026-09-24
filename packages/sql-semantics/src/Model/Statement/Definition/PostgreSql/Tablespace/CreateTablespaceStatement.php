<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Requests a PostgreSQL tablespace at a server directory, optionally owned by another role and with parameter overrides.
 * @visibility public
 * @example Reading the requested tablespace
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE fast OWNER alice LOCATION '/ssd' WITH (random_page_cost = 1.1)");
 *     $statement->owner->name // => 'alice'
 *     $statement->location->text // => "'/ssd'"
 *     $statement->parameters[0]->name->parts // => ['random_page_cost']
 * @example Rejecting an unknown tablespace parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE fast LOCATION '/ssd' WITH (random_page_cost = 1.1)");
 *     $statement->withParameters([new \SqlSemantics\Schema\Storage\Parameter(new \SqlSemantics\Model\Relation\QualifiedName(['fillfactor']), $statement->parameters[0]->value)]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateTablespaceStatement extends BoundStatement
{
    /**
     * @param list<Parameter> $parameters Parameter overrides in request order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly NamedRole|SessionRole|null $owner,
        public readonly Literal $location,
        public readonly array $parameters = [],
    ) {
        DatabaseInvariant::target($origin, $name);
        if ($location->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A tablespace location is a text constant.');
        }
        TablespaceInvariant::parameters($parameters);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the requested tablespace while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->owner, $this->location, $this->parameters);
    }

    /**
     * Replaces the tablespace name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->owner, $this->location, $this->parameters));
    }

    /**
     * Replaces or removes the owning role.
     */
    public function withOwner(NamedRole|SessionRole|null $owner): self
    {
        return $this->changed(new self($this->origin, $this->name, $owner, $this->location, $this->parameters));
    }

    /**
     * Replaces the server directory.
     */
    public function withLocation(Literal $location): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->owner, $location, $this->parameters));
    }

    /**
     * Replaces the parameter overrides.
     * @param list<Parameter> $parameters Replacement overrides
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->owner, $this->location, $parameters));
    }
}
