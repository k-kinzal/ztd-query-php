<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The role option INHERIT: the role inherits the privileges of the roles it is a member of.
 *
 * INHERIT is a keyword, so the grammar gives it a production of its own
 * instead of reading it as one of the plain attribute words; NOINHERIT is
 * such a word (`RoleAttribute`).
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the option INHERIT fills
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit())->option() // => 'inherit'
 */
final class RoleInherit implements RoleOption
{
    use Snapshot;

    /**
     * Answers the option INHERIT fills.
     */
    public function option(): string
    {
        return 'inherit';
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('INHERIT');
    }
}
