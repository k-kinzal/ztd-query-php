<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete stored role grant with an intrinsic account contradiction.
 *
 * It can be stored in an unreached procedure branch. Invocation preserves
 * recipient lookup, ordered grants, diagnostics and transaction effects.
 * It does not implement the ordinary statement role.
 *
 * @visibility public
 * @example Retaining a self-grant for conditional invocation
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT r TO r')->statement;
 *     $create->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles // => true
 */
final class DeferredGrantRoles implements ProgramStatement, PrivilegeChange
{
    use Snapshot;

    /**
     * @var non-empty-list<GrantedRole> The granted roles in order
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<Account> The accounts the roles are granted to
     */
    public readonly array $users;

    /**
     * @param list<GrantedRole> $roles The granted roles in order; at least one
     * @param list<Account> $users The accounts the roles are granted to; at least one
     * @param bool $withAdminOption Whether WITH ADMIN OPTION is written
     */
    public function __construct(array $roles, array $users, public readonly bool $withAdminOption = false)
    {
        $this->roles = Check::listOf($roles, GrantedRole::class, 'GRANT names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'GRANT names at least one account.', 1);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RoleGrants())->contradictory($this->roles, $this->users), 'A deferred role grant retains an anonymous operand or explicit self-grant.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: role operands have no relation or expression bindings.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Retains the complete instruction independently of source input.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles
    {
        return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles($this);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->roles)->keyword('TO')->list($this->users);
        if ($this->withAdminOption) {
            $out->keyword('WITH', 'ADMIN', 'OPTION');
        }
    }
}
