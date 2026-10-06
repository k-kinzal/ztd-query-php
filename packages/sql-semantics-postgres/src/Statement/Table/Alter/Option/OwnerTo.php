<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * OWNER TO: changes the owner.
 *
 * Mirrors `AT_ChangeOwner` with `newowner`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing the owner
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t OWNER TO CURRENT_USER');
 *     $statement->toString() // => 'ALTER TABLE t OWNER TO CURRENT_USER'
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
