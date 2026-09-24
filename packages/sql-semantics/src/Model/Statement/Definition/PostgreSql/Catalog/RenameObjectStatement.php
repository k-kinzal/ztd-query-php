<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Gives one catalog object a new unqualified name; the object keeps its schema and identity operands.
 * @visibility public
 * @example Renaming a procedural language
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old');
 *     $statement->object->kind->value // => 'LANGUAGE'
 *     $statement->newName // => 'plperl_old'
 * @example Rejecting a relation, which renames with its own existence policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER COLLATION c RENAME TO d');
 *     $statement->withObject(new \SqlSemantics\Model\Definition\Catalog\RelationIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Table, new \SqlSemantics\Model\Relation\QualifiedName(['t']))); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameObjectStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ObjectAddress $object, public readonly string $newName)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::rename($object);
        \SqlSemantics\Model\Definition\Catalog\CatalogInvariant::identifier($newName);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the object and its new name while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->newName);
    }

    /**
     * Replaces the renamed object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->newName));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->object, $newName));
    }
}
