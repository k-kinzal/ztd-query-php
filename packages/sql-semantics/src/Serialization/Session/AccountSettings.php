<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration as Statement;
use SqlSemantics\Model\Statement\ConfigurationStatement;

/**
 * Routes account activation, default roles, and credential changes to their concrete serializers.
 * @visibility SqlSemantics
 */
final class AccountSettings
{
    /**
     * Returns null when the configuration operation does not concern an account.
     */
    public static function write(ConfigurationStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\Role\SetRolePolicyStatement || $statement instanceof Statement\Role\SetExplicitRolesStatement || $statement instanceof Statement\Role\SetRolesExceptStatement || $statement instanceof Statement\Role\SetDefaultRolePolicyStatement || $statement instanceof Statement\Role\SetDefaultRolesStatement) {
            return Roles::write($statement);
        }
        if ($statement instanceof Statement\Password\SetPasswordStatement || $statement instanceof Statement\Password\SetPasswordHashStatement || $statement instanceof Statement\Password\SetDerivedPasswordStatement || $statement instanceof Statement\Password\SetRandomPasswordStatement || $statement instanceof Statement\Password\SetAccountOptionsStatement) {
            return Passwords::write($statement);
        }
        return null;
    }
}
