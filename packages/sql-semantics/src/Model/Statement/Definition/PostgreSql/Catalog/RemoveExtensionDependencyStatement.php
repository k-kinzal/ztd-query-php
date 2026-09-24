<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant as Names;
use SqlSemantics\Model\Definition\Catalog\RelationIdentity;
use SqlSemantics\Model\Definition\Catalog\RelationMemberIdentity;
use SqlSemantics\Model\Definition\Catalog\RoutineIdentity;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes the automatic dependency of a routine, trigger, materialized view, or index on an extension.
 * @visibility public
 * @example Reading the removed dependency
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix NO DEPENDS ON EXTENSION postgis');
 *     $statement->extension // => 'postgis'
 *     $statement->object->kind->value // => 'INDEX'
 */
final class RemoveExtensionDependencyStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RoutineIdentity|RelationMemberIdentity|RelationIdentity $object, public readonly string $extension)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::dependency($object);
        Names::identifier($extension);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the object and extension while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->extension);
    }

    /**
     * Replaces the dependent object in a separately validated statement.
     */
    public function withObject(RoutineIdentity|RelationMemberIdentity|RelationIdentity $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->extension));
    }

    /**
     * Replaces the extension the object depends on.
     */
    public function withExtension(string $extension): self
    {
        return $this->changed(new self($this->origin, $this->object, $extension));
    }
}
