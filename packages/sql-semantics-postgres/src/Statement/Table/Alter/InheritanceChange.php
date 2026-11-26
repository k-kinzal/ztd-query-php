<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;

/**
 * INHERIT or NO INHERIT: adds or removes a parent table.
 *
 * Mirrors `AT_AddInherit` and `AT_DropInherit`. The parent is resolved and is the relation fact of its node.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
