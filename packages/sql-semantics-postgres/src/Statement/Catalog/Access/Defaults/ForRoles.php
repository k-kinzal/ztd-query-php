<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;

/**
 * The clause FOR ROLE or FOR USER of ALTER DEFAULT PRIVILEGES: the defaults apply to objects these roles create.
 *
 * The two words request the same; the word is kept as written.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility SqlSemantics
 */
final class ForRoles implements DefaultScope
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The roles in the order written
     */
    public readonly array $roles;

    /**
     * @param RoleWord $word The word written: ROLE or USER
     * @param list<RoleSpec> $roles The roles in the order written, at least one
     */
    public function __construct(public readonly RoleWord $word, array $roles)
    {
        Check::input($word !== RoleWord::Group, 'FOR is followed by ROLE or USER.');
        $this->roles = Check::listOf($roles, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Answers the option the clause fills.
     */
    public function option(): string
    {
        return 'roles';
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR', $this->word->value)->list($this->roles);
    }
}
