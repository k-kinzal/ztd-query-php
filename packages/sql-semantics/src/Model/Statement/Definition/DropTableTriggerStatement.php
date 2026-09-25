<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one PostgreSQL trigger from its required owning table.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP TRIGGER IF EXISTS tr ON public.t CASCADE', strict: false);
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'DROP TRIGGER IF EXISTS "tr" ON "public"."t" CASCADE'
 *
 * @visibility public
 */
final class DropTableTriggerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly QualifiedName $table, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Dropping a table-owned trigger requires PostgreSQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the drop operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->table, $this->ifExists, $this->behavior);
    }
}
