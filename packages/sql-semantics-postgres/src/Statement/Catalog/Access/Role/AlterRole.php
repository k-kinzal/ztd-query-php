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
 * A request to change the attributes of a role, written as ALTER ROLE or ALTER USER.
 *
 * Rule: PG-ROLE-ALTER-001. Mirrors `AlterRoleStmt` with action +1: the
 * role and the options in the order written. ALTER accepts the options of
 * `AlterOptRoleElem` only, so SYSID, ADMIN, ROLE, IN ROLE and IN GROUP
 * cannot be written; USER lists members to add. The optional WITH is not
 * kept. Reported: PUBLIC or a `pg_` name as the role, and the option
 * problems of PG-ROLE-CHECK-001.
 * Source: https://www.postgresql.org/docs/17/sql-alterrole.html, https://www.postgresql.org/docs/17/sql-alteruser.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the role and its changed attribute
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE joe WITH NOSUPERUSER');
 *     [$operation->statement->role->name?->value, $operation->statement->options[0]->flag()?->enabled()] // => ['joe', false]
 */
final class AlterRole implements Statement
{
    use Snapshot;

    /**
     * @var list<RoleOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param RoleWord $word The statement word: ROLE or USER
     * @param RoleSpec $role The role to change
     * @param list<RoleOption> $options The options in the order written
     */
    public function __construct(public readonly RoleWord $word, public readonly RoleSpec $role, array $options = [])
    {
        Check::input($word !== RoleWord::Group, 'ALTER GROUP changes members only.');
        $this->options = Check::listOf($options, RoleOption::class, 'Role options are a list of role options.');
        foreach ($this->options as $option) {
            Check::input(!$option instanceof RoleSystemId && (!$option instanceof RoleMembers || $option->kind === RoleMembersKind::User), 'ALTER ROLE accepts no option that only CREATE ROLE has.');
        }
    }

    /**
     * Reports the problems of the role and of the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new RoleChecks();
        $checks->alterable($derivation, $this->role);
        $checks->options($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', $this->word->value)->node($this->role);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
