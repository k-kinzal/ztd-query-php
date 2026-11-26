<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

/**
 * The roles SET ROLE, a DEFAULT ROLE clause or GRANT … AS … WITH ROLE select: `role, …`, `NONE`, `DEFAULT` or `ALL [EXCEPT role, …]`.
 *
 * The roles are the named ones for Named and the excepted ones for All.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-role.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-default-role.html.
 *
 * @visibility SqlSemantics
 */
final class RoleSelection implements Node
{
    use Snapshot;

    /**
     * @var list<AccountName> The named roles, or the roles EXCEPT names
     */
    public readonly array $roles;

    /**
     * @param RoleSet $set Which roles are selected
     * @param list<AccountName> $roles The named roles (at least one), or the roles EXCEPT names; none otherwise
     */
    public function __construct(public readonly RoleSet $set, array $roles = [])
    {
        $this->roles = Check::listOf($roles, AccountName::class, 'A role selection names roles.');
        Check::input(match ($set) {
            RoleSet::Named => $this->roles !== [],
            RoleSet::All => true,
            RoleSet::None, RoleSet::Default => $this->roles === [],
        }, 'Roles are named by a selection of named roles and excepted by a selection of all roles.');
    }

    /**
     * Writes the selection.
     */
    public function render(Output $out): void
    {
        match ($this->set) {
            RoleSet::Named => $out->list($this->roles),
            RoleSet::None => $out->keyword('NONE'),
            RoleSet::Default => $out->keyword('DEFAULT'),
            RoleSet::All => $out->keyword('ALL'),
        };
        if ($this->set === RoleSet::All && $this->roles !== []) {
            $out->keyword('EXCEPT')->list($this->roles);
        }
    }
}
