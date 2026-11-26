<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * OWNER TO: changes the owner.
 *
 * Mirrors `AT_ChangeOwner` with `newowner`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class OwnerTo implements AlterCommand
{
    use Snapshot;

    /**
     * @param RoleSpec $owner The new owner
     */
    public function __construct(public readonly RoleSpec $owner)
    {
    }

    /**
     * Derives nothing: the role is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes OWNER TO and the role.
     */
    public function render(Output $out): void
    {
        $out->keyword('OWNER', 'TO')->node($this->owner);
    }
}
