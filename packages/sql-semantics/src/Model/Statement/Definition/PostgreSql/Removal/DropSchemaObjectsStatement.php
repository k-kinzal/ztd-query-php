<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\SchemaObjectKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops collations, conversions, statistics objects, or text search objects by qualified name.
 * @visibility public
 * @example Dropping text search configurations
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT');
 *     $statement->objectKind // => \SqlSemantics\Model\Definition\Catalog\Kind\SchemaObjectKind::TextSearchConfiguration
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Restrict
 */
final class DropSchemaObjectsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly SchemaObjectKind $objectKind, public readonly array $names, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        RemovalInvariant::names($origin, $names, 2);
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
     * Replaces the object class.
     */
    public function withObjectKind(SchemaObjectKind $objectKind): self
    {
        return $this->changed(new self($this->origin, $objectKind, $this->names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the removed objects.
     * @param non-empty-list<QualifiedName> $names
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
