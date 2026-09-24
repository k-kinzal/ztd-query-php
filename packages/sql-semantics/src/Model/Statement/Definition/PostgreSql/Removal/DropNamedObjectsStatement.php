<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops access methods, extensions, languages, publications, or schemas by unqualified name.
 * Servers, wrappers, and event triggers have their own removal statements.
 * @visibility public
 * @example Dropping schemas
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE');
 *     $statement->objectKind // => \SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::Schema
 *     $statement->names // => ['app', 'archive']
 * @example Rejecting an object class with its own removal statement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP EXTENSION e');
 *     $statement->withObjectKind(\SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::Server); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropNamedObjectsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string> $names
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly NamedObjectKind $objectKind, public readonly array $names, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        if (!in_array($objectKind, [NamedObjectKind::AccessMethod, NamedObjectKind::Extension, NamedObjectKind::Language, NamedObjectKind::Publication, NamedObjectKind::Schema], true)) {
            throw new InvalidStructure('The object class is removed by its own statement form.');
        }
        RemovalInvariant::identifiers($origin, $names);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->objectKind, $this->names, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the object class within the classes this form removes.
     */
    public function withObjectKind(NamedObjectKind $objectKind): self
    {
        return $this->changed(new self($this->origin, $objectKind, $this->names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the removed objects.
     * @param non-empty-list<string> $names
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $this->objectKind, $names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for missing objects.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->objectKind, $this->names, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->objectKind, $this->names, $this->ifExists, $behavior));
    }
}
