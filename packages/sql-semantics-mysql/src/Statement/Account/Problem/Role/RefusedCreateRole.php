<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\Role;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * The intrinsic account contradiction of a complete CREATE ROLE request.
 *
 * @visibility public
 * @example Describing an anonymous role definition
 *     $request = new \SqlSemantics\Platform\MySql\Statement\Account\CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(''))]);
 *     $request->problem->message() // => 'CREATE ROLE cannot define an anonymous account.'
 */
final class RefusedCreateRole implements Diagnostic
{
    use Snapshot;

    /**
     * @var non-empty-list<AccountName> The complete ordered account operands
     */
    public readonly array $roles;

    /**
     * @param bool $ifNotExists Whether existing accounts can be skipped
     * @param list<AccountName> $roles The complete ordered account operands
     */
    public function __construct(public readonly bool $ifNotExists, array $roles)
    {
        $this->roles = Check::listOf($roles, AccountName::class, 'A role definition condition describes at least one account.', 1);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Account\RoleDefinitions())->problem($ifNotExists, $this->roles) !== null, 'A role definition condition requires contradictory account identities.');
    }

    /**
     * Describes the fixed condition independently of account catalog state.
     */
    public function message(): string
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Account\RoleDefinitions())->problem($this->ifNotExists, $this->roles);
        Check::invariant($problem !== null, 'A refused role definition has an intrinsic account contradiction.');
        return $problem;
    }
}
