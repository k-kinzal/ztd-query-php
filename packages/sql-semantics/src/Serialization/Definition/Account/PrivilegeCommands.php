<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege as Statement;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes MySQL GRANT and REVOKE from their typed privileges, levels, recipients, and policies.
 * @visibility SqlSemantics
 */
final class PrivilegeCommands
{
    /**
     * Returns null for statements outside the privilege family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\GrantRolesStatement => new Tree('grant-roles', [Build::keyword('GRANT'), Roles::accounts($statement->roles), Build::keyword('TO'), Accounts::list($statement->grantees), ...($statement->withAdminOption ? [Build::keyword('WITH ADMIN OPTION')] : [])]),
            $statement instanceof Statement\GrantProxyStatement => new Tree('grant-proxy', [Build::keyword('GRANT PROXY ON'), Accounts::write($statement->proxied), Build::keyword('TO'), PrivilegeLevels::grantees($statement->grantees), ...($statement->withGrantOption ? [Build::keyword('WITH GRANT OPTION')] : [])]),
            $statement instanceof Statement\GrantPrivilegesStatement, $statement instanceof Statement\GrantAllPrivilegesStatement => self::grant($statement),
            $statement instanceof Statement\RevokeRolesStatement => new Tree('revoke-roles', [self::revoke($statement->ifExists), Roles::accounts($statement->roles), Build::keyword('FROM'), Accounts::list($statement->grantees), ...self::ignore($statement->ignoreUnknownUser)]),
            $statement instanceof Statement\RevokeAllGrantsStatement => new Tree('revoke-all-grants', [self::revoke($statement->ifExists), Build::keyword('ALL PRIVILEGES, GRANT OPTION FROM'), Accounts::list($statement->grantees), ...self::ignore($statement->ignoreUnknownUser)]),
            $statement instanceof Statement\RevokeProxyStatement => new Tree('revoke-proxy', [self::revoke($statement->ifExists), Build::keyword('PROXY ON'), Accounts::write($statement->proxied), Build::keyword('FROM'), Accounts::list($statement->grantees), ...self::ignore($statement->ignoreUnknownUser)]),
            $statement instanceof Statement\RevokePrivilegesStatement, $statement instanceof Statement\RevokeAllPrivilegesStatement => new Tree('revoke-privileges', [
                self::revoke($statement->ifExists),
                $statement instanceof Statement\RevokeAllPrivilegesStatement ? Build::keyword('ALL PRIVILEGES') : PrivilegeLevels::privileges($statement->privileges),
                Build::keyword('ON'),
                PrivilegeLevels::level($statement->target),
                Build::keyword('FROM'),
                Accounts::list($statement->grantees),
                ...self::ignore($statement->ignoreUnknownUser),
            ]),
            default => null,
        };
    }

    /**
     * Named and all-privilege grants share their level, recipients, requirement, options, and grantor.
     */
    public static function grant(Statement\GrantPrivilegesStatement|Statement\GrantAllPrivilegesStatement $statement): Tree
    {
        return new Tree('grant-privileges', [
            Build::keyword('GRANT'),
            $statement instanceof Statement\GrantAllPrivilegesStatement ? Build::keyword('ALL PRIVILEGES') : PrivilegeLevels::privileges($statement->privileges),
            Build::keyword('ON'),
            PrivilegeLevels::level($statement->target),
            Build::keyword('TO'),
            PrivilegeLevels::grantees($statement->grantees),
            ...AccountClauses::requirement($statement->requirement),
            ...AccountClauses::limits($statement->resourceLimits, $statement->withGrantOption),
            ...PrivilegeLevels::grantor($statement->grantor),
        ]);
    }

    /**
     * REVOKE with its optional existence policy.
     */
    public static function revoke(bool $ifExists): Tree
    {
        return Build::keyword('REVOKE' . ($ifExists ? ' IF EXISTS' : ''));
    }

    /**
     * @return list<Tree>
     */
    public static function ignore(bool $ignoreUnknownUser): array
    {
        return $ignoreUnknownUser ? [Build::keyword('IGNORE UNKNOWN USER')] : [];
    }
}
