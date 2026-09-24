<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant as Names;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Moves one schema-scoped catalog object into another schema.
 * @visibility public
 * @example Moving an aggregate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\AggregateIdentity // => true
 *     $statement->schema // => 'stats'
 */
final class SetObjectSchemaStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ObjectAddress $object, public readonly string $schema)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::schema($object);
        Names::identifier($schema);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the object and destination while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->schema);
    }

    /**
     * Replaces the moved object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->schema));
    }

    /**
     * Replaces the destination schema.
     */
    public function withSchema(string $schema): self
    {
        return $this->changed(new self($this->origin, $this->object, $schema));
    }
}
