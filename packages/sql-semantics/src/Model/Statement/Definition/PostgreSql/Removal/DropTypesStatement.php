<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\TypeKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Drops types or domains addressed by type declarations.
 * @visibility public
 * @example Dropping domains
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent');
 *     $statement->typeKind // => \SqlSemantics\Model\Definition\Catalog\Kind\TypeKind::Domain
 *     $statement->types[1]->name // => 'app.percent'
 * @example Rejecting an empty type list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP TYPE t');
 *     $statement->withTypes([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropTypesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<TypeDescriptor> $types
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TypeKind $typeKind, public readonly array $types, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        RemovalInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($types), TypeDescriptor::class);
        foreach ($types as $type) {
            if ($type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Removed types require PostgreSQL type declarations.');
            }
        }
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
        return new self($origin, $this->typeKind, $this->types, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the type class.
     */
    public function withTypeKind(TypeKind $typeKind): self
    {
        return $this->changed(new self($this->origin, $typeKind, $this->types, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the removed types.
     * @param non-empty-list<TypeDescriptor> $types
     */
    public function withTypes(array $types): self
    {
        return $this->changed(new self($this->origin, $this->typeKind, $types, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for missing types.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->typeKind, $this->types, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->typeKind, $this->types, $this->ifExists, $behavior));
    }
}
