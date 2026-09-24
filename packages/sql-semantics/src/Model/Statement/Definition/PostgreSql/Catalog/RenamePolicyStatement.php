<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant as Names;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames a row security policy of one table; a missing policy may be tolerated.
 * @visibility public
 * @example Reading a tolerant policy rename
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners');
 *     $statement->ifExists // => true
 *     $statement->table->parts // => ['app', 'docs']
 */
final class RenamePolicyStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly QualifiedName $table, public readonly bool $ifExists, public readonly string $newName)
    {
        CatalogInvariant::dialect($origin);
        Names::identifier($name);
        Names::identifier($newName);
        Names::name($table, 3);
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
        return new self($origin, $this->name, $this->table, $this->ifExists, $this->newName);
    }

    /**
     * Replaces the policy's current name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->ifExists, $this->newName));
    }

    /**
     * Replaces the owning table.
     */
    public function withTable(QualifiedName $table): self
    {
        return $this->changed(new self($this->origin, $this->name, $table, $this->ifExists, $this->newName));
    }

    /**
     * Replaces the tolerance for a missing policy.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $ifExists, $this->newName));
    }

    /**
     * Replaces the requested new name.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->ifExists, $newName));
    }
}
