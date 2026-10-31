<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\Role;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * The intrinsic account contradiction of a complete role-grant request.
 *
 * @visibility public
 * @example Describing a contradictory role grant
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT '' TO u");
 *     $create->body->problem->message() // => 'GRANT cannot use anonymous accounts or grant an explicit account to itself.'
 */
final class RefusedGrantRoles implements Diagnostic
{
    use Snapshot;

    /**
     * @var non-empty-list<GrantedRole> The complete ordered role operands
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<Account> The complete ordered recipient operands
     */
    public readonly array $users;

    /**
     * @param list<GrantedRole> $roles The complete ordered role operands
     * @param list<Account> $users The complete ordered recipient operands
     */
    public function __construct(array $roles, array $users)
    {
        $this->roles = Check::listOf($roles, GrantedRole::class, 'A role grant condition describes at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'A role grant condition describes at least one recipient.', 1);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RoleGrants())->contradictory($this->roles, $this->users), 'A role grant condition requires contradictory account operands.');
    }

    /**
     * Describes the fixed condition independently of account catalog state.
     */
    public function message(): string
    {
        return 'GRANT cannot use anonymous accounts or grant an explicit account to itself.';
    }
}
