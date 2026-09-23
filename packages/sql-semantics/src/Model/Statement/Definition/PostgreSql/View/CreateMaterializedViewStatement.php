<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\View;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Declares a query whose result rows are stored and read like a table until refreshed.
 *
 * @visibility public
 * @example Inspecting the population request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW mv AS SELECT 1 WITH NO DATA');
 *     [$statement->withData, $statement->name->parts] // => [false, ['mv']]
 */
final class CreateMaterializedViewStatement extends BoundStatement
{
    /**
     * @param list<string> $columns Declared result names
     * @param list<Parameter> $storageParameters Declared storage options
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly BoundQuery $query,
        public readonly array $columns = [],
        public readonly bool $unlogged = false,
        public readonly bool $ifNotExists = false,
        public readonly ?string $accessMethod = null,
        public readonly array $storageParameters = [],
        public readonly ?string $tablespace = null,
        public readonly bool $withData = true,
    ) {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A materialized view requires PostgreSQL.');
        }
        Collections::strings($columns);
        Collections::objects($storageParameters, Parameter::class);
        if ($accessMethod === '' || $tablespace === '') {
            throw new InvalidStructure('A storage name requires at least one character.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->query, $this->columns, $this->unlogged, $this->ifNotExists, $this->accessMethod, $this->storageParameters, $this->tablespace, $this->withData);
    }

    /**
     * Replaces the stored query and revalidates the declaration against the schema.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $this->name, $query, $this->columns, $this->unlogged, $this->ifNotExists, $this->accessMethod, $this->storageParameters, $this->tablespace, $this->withData));
    }

    /**
     * Changes whether the declaration populates rows immediately.
     * @throws InvalidStructure
     */
    public function withPopulation(bool $withData): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->query, $this->columns, $this->unlogged, $this->ifNotExists, $this->accessMethod, $this->storageParameters, $this->tablespace, $withData));
    }
}
