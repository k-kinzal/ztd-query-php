<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The REVOKE action of ALTER DEFAULT PRIVILEGES: privileges future objects of a kind no longer receive.
 *
 * Mirrors the `GrantStmt` of `DefACLAction` without `is_grant`: the
 * privileges (an empty list is ALL PRIVILEGES), the kind of object, the
 * roles, GRANT OPTION FOR and CASCADE or RESTRICT as written.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility public
 * @example Reading a default revoke
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON FUNCTIONS FROM joe RESTRICT');
 *     [$operation->statement->action->grantOptionOnly, $operation->statement->action->behavior?->value] // => [true, 'RESTRICT']
 */
final class DefaultRevoke implements Node
{
    use Snapshot;

    /**
     * @var list<Privilege> The privileges in the order written; empty for ALL PRIVILEGES
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<RoleSpec> The roles in the order written
     */
    public readonly array $grantees;

    /**
     * @param list<Privilege> $privileges The privileges in the order written; empty for ALL PRIVILEGES
     * @param DefaultObjectKind $objects The kind of object the defaults are set for
     * @param list<RoleSpec> $grantees The roles the privileges are revoked from, at least one
     * @param bool $grantOptionOnly Whether GRANT OPTION FOR is written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(array $privileges, public readonly DefaultObjectKind $objects, array $grantees, public readonly bool $grantOptionOnly = false, public readonly ?DropBehavior $behavior = null)
    {
        $this->privileges = (new PrivilegeLists())->privileges($privileges);
        $this->grantees = Check::listOf($grantees, RoleSpec::class, 'A revoke names at least one role.', 1);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('REVOKE');
        if ($this->grantOptionOnly) {
            $out->keyword('GRANT', 'OPTION', 'FOR');
        }
        (new PrivilegeLists())->write($out, $this->privileges);
        $out->keyword('ON', $this->objects->value, 'FROM')->list($this->grantees);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
