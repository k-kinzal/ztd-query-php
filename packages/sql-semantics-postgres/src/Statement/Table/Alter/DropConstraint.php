<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * DROP CONSTRAINT: removes a constraint.
 *
 * Mirrors `AT_DropConstraint` with `missing_ok` and `behavior`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class DropConstraint implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $name The constraint
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(
        public readonly Name $name,
        public readonly bool $ifExists = false,
        public readonly ?DropBehavior $behavior = null,
    ) {
    }

    /**
     * Derives nothing: the constraint is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'CONSTRAINT');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
