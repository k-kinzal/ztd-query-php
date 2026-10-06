<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove roles, written as DROP ROLE, DROP USER or DROP GROUP.
 *
 * Rule: PG-ROLE-DROP-001. Mirrors `DropRoleStmt`: the roles in the order
 * written and IF EXISTS; the statement word is kept as written. Reported: a
 * role given as PUBLIC, CURRENT_ROLE, CURRENT_USER or SESSION_USER, which
 * DROP ROLE does not accept.
 * Source: https://www.postgresql.org/docs/17/sql-droprole.html, https://www.postgresql.org/docs/17/sql-dropuser.html,
 * https://www.postgresql.org/docs/17/sql-dropgroup.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the removed roles
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP ROLE IF EXISTS joe, ann');
 *     [$operation->statement->ifExists, $operation->statement->roles[1]->name?->value] // => [true, 'ann']
 */
final class DropRole implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The roles in the order written
     */
    public readonly array $roles;

    /**
     * @param RoleWord $word The statement word: ROLE, USER or GROUP
     * @param list<RoleSpec> $roles The roles in the order written, at least one
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly RoleWord $word, array $roles, public readonly bool $ifExists = false)
    {
        $this->roles = Check::listOf($roles, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Reports the roles that are not given by name.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RoleChecks())->droppable($derivation, $this->roles);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', $this->word->value);
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->roles);
    }
}
