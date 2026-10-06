<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * INHERIT or NO INHERIT: adds or removes a parent table.
 *
 * Mirrors `AT_AddInherit` and `AT_DropInherit`. The parent is resolved and is the relation fact of its node.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Removing a parent
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t NO INHERIT p');
 *     $statement->statement->commands[0]->remove // => true
 */
final class InheritanceChange implements AlterCommand
{
    use Snapshot;

    /**
     * @param ParentTable $parent The parent table
     * @param bool $remove Whether the parent is removed (NO INHERIT); added otherwise
     */
    public function __construct(public readonly ParentTable $parent, public readonly bool $remove = false)
    {
    }

    /**
     * Resolves the parent.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->target($this->parent, (new Targets())->resolve($derivation, $this->parent->name));
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        if ($this->remove) {
            $out->keyword('NO');
        }
        $out->keyword('INHERIT')->node($this->parent);
    }
}
