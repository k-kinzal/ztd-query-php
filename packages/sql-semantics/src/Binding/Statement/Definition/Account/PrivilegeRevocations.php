<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeAllGrantsStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeAllPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeProxyStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the five REVOKE forms: roles, named privileges, all privileges at one level, every grant, and proxying.
 * @visibility SqlSemantics
 */
final class PrivilegeRevocations
{
    /**
     * Legacy grammars wrap the body in revoke_command; MySQL 8 keeps it in the revoke node with its policies.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): BoundStatement
    {
        $body = Tree::child($statement, ['revoke_command']) ?? $statement;
        $identifiers = $context->tables->identifiers;
        $ifExists = Tree::child($statement, ['if_exists']) !== null;
        $ignore = Tree::child($statement, ['opt_ignore_unknown_user']) !== null;
        $grantees = self::grantees($body, $identifiers);
        $list = Tree::child($body, ['role_or_privilege_list', 'grant_privileges']);
        $proxied = Tree::child($body, ['user']);
        try {
            if (Tree::child($body, ['grant_ident']) === null) {
                if (PrivilegeLists::all($body)) {
                    return new RevokeAllGrantsStatement($origin, $grantees, $ifExists, $ignore);
                }
                if ($list === null && $proxied !== null) {
                    return new RevokeProxyStatement($origin, Accounts::account($proxied, $identifiers), $grantees, $ifExists, $ignore);
                }
                $list ??= throw new UnclassifiedSql('A role revocation requires its roles.');
                return new RevokeRolesStatement($origin, PrivilegeLists::roles($list, $identifiers), $grantees, $ifExists, $ignore);
            }
            $target = PrivilegeLevels::read($origin, $body, $context);
            if (PrivilegeLists::all($body)) {
                return new RevokeAllPrivilegesStatement($origin, $target, $grantees, $ifExists, $ignore);
            }
            $list ??= throw new UnclassifiedSql('A privilege revocation requires its privileges.');
            return new RevokePrivilegesStatement($origin, PrivilegeLists::privileges($list, $identifiers), $target, $grantees, $ifExists, $ignore);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PrivilegeLevel, $body, $error);
        }
    }

    /**
     * Accounts losing grants are plain identities; a MySQL 5.6 credential on a revocation is impossible.
     * @return non-empty-list<AccountName|CurrentAccount>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function grantees(Node $body, Identifiers $identifiers): array
    {
        $list = Tree::child($body, ['user_list']);
        if ($list !== null) {
            return Accounts::list($list, $identifiers);
        }
        $list = Tree::child($body, ['grant_list']) ?? throw new UnclassifiedSql('A revocation requires its accounts.');
        $grantees = [];
        foreach (Tree::outer($list, ['grant_user']) as $grantee) {
            $account = UserDefinitions::grantee($grantee, $identifiers);
            if ($account instanceof \SqlSemantics\Model\Definition\Account\AccountDefinition) {
                throw new InvalidSql(InputViolation::RevokedCredential, $grantee);
            }
            $grantees[] = $account;
        }
        if ($grantees === []) {
            throw new UnclassifiedSql('A revocation requires at least one account.');
        }
        return Collections::nonEmpty($grantees);
    }
}
