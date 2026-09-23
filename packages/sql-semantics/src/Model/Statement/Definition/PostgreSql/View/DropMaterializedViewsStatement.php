<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\View;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes stored materialized views; ordinary views use their own removal form.
 *
 * @visibility public
 * @example Inspecting the removal targets
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW IF EXISTS mv, app.mv2 CASCADE');
 *     [count($statement->names), $statement->ifExists, $statement->behavior->value] // => [2, true, 'CASCADE']
 */
final class DropMaterializedViewsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<QualifiedName>
     */
    public readonly array $names;

    /**
     * @param list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $names, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A materialized view requires PostgreSQL.');
        }
        Collections::objects($names, QualifiedName::class);
        $this->names = Collections::nonEmpty($names);
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->names, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the removal targets while keeping the existence and dependency policies.
     * @param list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $names, $this->ifExists, $this->behavior));
    }
}
