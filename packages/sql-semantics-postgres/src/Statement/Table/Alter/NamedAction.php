<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation action on one named object.
 *
 * One `AlterTableCmd` with a `name`; see `NamedActionKind`. The named index, constraint, tablespace, trigger
 * or rule is looked up by the server; a version 1 context declares none of them.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class NamedAction implements AlterCommand
{
    use Snapshot;

    /**
     * @param NamedActionKind $kind The action
     * @param Name $name The object
     */
    public function __construct(public readonly NamedActionKind $kind, public readonly Name $name)
    {
    }

    /**
     * Derives nothing: the object is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword(...$this->kind->keywords())->name($this->name);
    }
}
