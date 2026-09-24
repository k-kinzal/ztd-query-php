<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\RoleExclusion;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantAllPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantProxyStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the four GRANT forms: roles, named privileges, all privileges, and proxying.
 * @visibility SqlSemantics
 */
final class PrivilegeGrants
{
    /**
     * Legacy grammars wrap the body in grant_command; MySQL 8 keeps it in the grant node.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): BoundStatement
    {
        $body = Tree::child($statement, ['grant_command']) ?? $statement;
        $identifiers = $context->tables->identifiers;
        $list = Tree::child($body, ['role_or_privilege_list', 'grant_privileges']);
        try {
            $proxied = Tree::child($body, ['user']);
            if ($list === null && $proxied !== null) {
                return new GrantProxyStatement($origin, Accounts::account($proxied, $identifiers), self::grantees($body, $identifiers), Tree::child($body, ['opt_grant_option']) !== null);
            }
            if (Tree::child($body, ['grant_ident']) === null) {
                $list ??= throw new UnclassifiedSql('A role grant requires its roles.');
                return new GrantRolesStatement($origin, PrivilegeLists::roles($list, $identifiers), Accounts::list(Tree::child($body, ['user_list']) ?? throw new UnclassifiedSql('A role grant requires its recipients.'), $identifiers), Tree::child($body, ['opt_with_admin_option']) !== null);
            }
            $target = PrivilegeLevels::read($origin, $body, $context);
            $grantees = self::grantees($body, $identifiers);
            $option = AccountClauses::grantOption($body, 'grant_options');
            $requirement = AccountClauses::requirement($body);
            $limits = AccountClauses::limits($body);
            $grantor = self::grantor($body, $identifiers);
            if (PrivilegeLists::all($body)) {
                return new GrantAllPrivilegesStatement($origin, $target, $grantees, $option, $requirement, $limits, $grantor);
            }
            $list ??= throw new UnclassifiedSql('A privilege grant requires its privileges.');
            return new GrantPrivilegesStatement($origin, PrivilegeLists::privileges($list, $identifiers), $target, $grantees, $option, $requirement, $limits, $grantor);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PrivilegeLevel, $body, $error);
        }
    }

    /**
     * MySQL 8 recipients are plain accounts; legacy recipients may carry one credential.
     * @return non-empty-list<AccountName|CurrentAccount|AccountDefinition>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function grantees(Node $body, Identifiers $identifiers): array
    {
        $list = Tree::child($body, ['user_list']);
        if ($list !== null) {
            return Accounts::list($list, $identifiers);
        }
        $list = Tree::child($body, ['grant_list']) ?? throw new UnclassifiedSql('A grant requires its recipients.');
        $grantees = array_map(static fn (Node $grantee): AccountName|CurrentAccount|AccountDefinition => UserDefinitions::grantee($grantee, $identifiers), Tree::outer($list, ['grant_user']));
        if ($grantees === []) {
            throw new UnclassifiedSql('A grant requires at least one recipient.');
        }
        return Collections::nonEmpty($grantees);
    }

    /**
     * AS user [WITH ROLE ...] selects the account and role context whose privileges are checked.
     * @throws UnclassifiedSql
     */
    public static function grantor(Node $body, Identifiers $identifiers): ?Grantor
    {
        $clause = Tree::child($body, ['opt_grant_as']);
        if ($clause === null) {
            return null;
        }
        $user = Tree::child($clause, ['user']) ?? throw new UnclassifiedSql('A grantor context requires its account.');
        $roles = Tree::child($clause, ['opt_with_roles']);
        if ($roles === null) {
            return new Grantor(Accounts::account($user, $identifiers));
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $roles->tokens());
        $list = Tree::outer($roles, ['role_list'])[0] ?? null;
        $selection = match (true) {
            $list !== null && ($words[2] ?? '') !== 'ALL' => new RoleSelection(AccountNames::read($list, $identifiers)),
            $list !== null => new RoleExclusion(AccountNames::read($list, $identifiers)),
            default => SessionRolePolicy::tryFrom($words[2] ?? '') ?? throw new UnclassifiedSql('Unclassified grantor role selection: ' . Tree::text($roles)),
        };
        return new Grantor(Accounts::account($user, $identifiers), $selection);
    }
}
