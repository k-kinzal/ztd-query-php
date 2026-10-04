<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A privilege in the role list of GRANT … TO or REVOKE … FROM, or a role written with a host in a privilege list.
 *
 * The grammar accepts both, and the server then rejects the statement with
 * a syntax error: "Illegal authorization identifier" for a privilege read as
 * a role, "Illegal privilege identifier" for a role read as a privilege
 * (PT_role_or_privilege::get_user and get_privilege).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch('SELECT', true))->message() // => 'SELECT is not a role: illegal authorization identifier.'
 */
final class RoleOrPrivilegeMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $item The item as a person reads it
     * @param bool $roleExpected Whether the statement reads the item as a role
     */
    public function __construct(public readonly string $item, public readonly bool $roleExpected)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->roleExpected
            ? $this->item . ' is not a role: illegal authorization identifier.'
            : $this->item . ' is not a privilege: illegal privilege identifier.';
    }
}
