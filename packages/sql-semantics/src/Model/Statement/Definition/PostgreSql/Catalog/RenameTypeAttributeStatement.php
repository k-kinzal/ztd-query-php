<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant as Names;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames one attribute of a composite type, with a policy for typed tables that depend on it.
 * @visibility public
 * @example Reading a cascading attribute rename
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE');
 *     $statement->attribute // => 'x'
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class RenameTypeAttributeStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $type, public readonly string $attribute, public readonly string $newName, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        CatalogInvariant::dialect($origin);
        Names::name($type, 2);
        Names::identifier($attribute);
        Names::identifier($newName);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->type, $this->attribute, $this->newName, $this->behavior);
    }

    /**
     * Replaces the composite type.
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->attribute, $this->newName, $this->behavior));
    }

    /**
     * Replaces the attribute's current name.
     */
    public function withAttribute(string $attribute): self
    {
        return $this->changed(new self($this->origin, $this->type, $attribute, $this->newName, $this->behavior));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->attribute, $newName, $this->behavior));
    }

    /**
     * Replaces the policy for dependent typed tables.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->attribute, $this->newName, $behavior));
    }
}
