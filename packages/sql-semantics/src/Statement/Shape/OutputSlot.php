<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * One output position of a relation occurrence or of a query.
 *
 * A slot is not a declaration. A slot of a named table refers to the declared
 * column; a slot a join re-exposes refers to the input slot it comes from and
 * carries its own NULL fact, so an outer join never changes a declaration.
 *
 * @visibility public
 * @example Reading the slot of a query field
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)');
 *     $semantics->analyze('SELECT a FROM t', [$table])->field('a')->slot->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
final class OutputSlot
{
    use Snapshot;

    /**
     * @param Name|null $name The output name, or null when the position has none
     * @param TypeFact $type What is known about the type at this position
     * @param Nullability $nullability Whether the position can be NULL
     * @param Column|null $column The declared column when the position is that column itself
     * @param OutputSlot|null $origin The input slot this position re-exposes, for example through a join
     */
    public function __construct(
        public readonly ?Name $name,
        public readonly TypeFact $type,
        public readonly Nullability $nullability,
        public readonly ?Column $column = null,
        public readonly ?OutputSlot $origin = null,
    ) {
    }

    /**
     * Follows re-exposed slots back to the declared column, when the position is one.
     */
    public function declaration(): ?Column
    {
        $slot = $this;
        while ($slot->column === null && $slot->origin !== null) {
            $slot = $slot->origin;
        }

        return $slot->column;
    }
}
