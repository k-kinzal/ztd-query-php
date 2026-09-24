<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateRolesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds CREATE USER in its MySQL 8 and legacy list forms, and CREATE ROLE.
 * @visibility SqlSemantics
 */
final class UserDefinitions
{
    /**
     * Account definitions come from create_user nodes in MySQL 8 and grant_user nodes before it.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, Identifiers $identifiers): CreateUsersStatement
    {
        $accounts = [];
        foreach (Tree::outer($statement, ['create_user', 'grant_user']) as $definition) {
            $accounts[] = $definition->name === 'create_user' ? self::definition($definition, $identifiers) : self::legacy($definition, $identifiers);
        }
        if ($accounts === []) {
            throw new UnclassifiedSql('Account creation requires at least one account.');
        }
        $roles = Tree::child($statement, ['default_role_clause']);
        return new CreateUsersStatement(
            $origin,
            Collections::nonEmpty($accounts),
            Tree::child($statement, ['opt_if_not_exists']) !== null,
            $roles === null ? [] : AccountNames::read($roles, $identifiers),
            AccountClauses::requirement($statement),
            AccountClauses::limits($statement),
            AccountClauses::policies($statement),
            AccountClauses::annotation($statement),
        );
    }

    /**
     * A plugin with INITIAL AUTHENTICATION is the passwordless form; otherwise the first factor may carry two more.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function definition(Node $definition, Identifiers $identifiers): AccountDefinition|InitialAuthenticationDefinition
    {
        $user = Tree::child($definition, ['user']) ?? throw new UnclassifiedSql('An account definition requires its account.');
        $account = Accounts::account($user, $identifiers);
        $initial = Tree::child($definition, ['opt_initial_auth']);
        if ($initial !== null) {
            $plugin = Tree::child($definition, ['identified_with_plugin']) ?? throw new UnclassifiedSql('Initial authentication requires the passwordless plugin.');
            $credential = Tree::significant($initial);
            $form = $credential[count($credential) - 1];
            if (!$form instanceof Node) {
                throw new UnclassifiedSql('Initial authentication requires its credential form.');
            }
            $first = Identifications::read($form, $identifiers);
            if ($first instanceof \SqlSemantics\Model\Definition\Account\Identification\PluginIdentification || $first instanceof \SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification || $first instanceof \SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification) {
                throw new UnclassifiedSql('Unclassified initial authentication form: ' . Tree::text($initial));
            }
            return new InitialAuthenticationDefinition($account, Identifications::plugin($plugin, $identifiers), $first);
        }
        $identification = Tree::child($definition, ['identification']);
        $factors = [];
        $additional = Tree::child($definition, ['opt_create_user_with_mfa']);
        foreach ($additional === null ? [] : Tree::outer($additional, ['identification']) as $factor) {
            $factors[] = Identifications::read($factor, $identifiers);
        }
        return new AccountDefinition($account, $identification === null ? null : Identifications::read($identification, $identifiers), $factors);
    }

    /**
     * A legacy grantee or definition names its account and at most one credential.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function legacy(Node $grantUser, Identifiers $identifiers): AccountDefinition
    {
        $user = Tree::child($grantUser, ['user']) ?? throw new UnclassifiedSql('An account definition requires its account.');
        return new AccountDefinition(Accounts::account($user, $identifiers), Identifications::legacy($grantUser, array_slice($grantUser->tokens(), count($user->tokens())), $identifiers));
    }

    /**
     * A legacy grantee without a credential stays a plain account identity.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function grantee(Node $grantUser, Identifiers $identifiers): AccountName|CurrentAccount|AccountDefinition
    {
        $definition = self::legacy($grantUser, $identifiers);
        return $definition->identification === null ? $definition->account : $definition;
    }

    /**
     * CREATE ROLE lists role names with optional hosts.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function roles(Origin $origin, Node $statement, Identifiers $identifiers): CreateRolesStatement
    {
        return new CreateRolesStatement($origin, AccountNames::read($statement, $identifiers), Tree::child($statement, ['opt_if_not_exists']) !== null);
    }
}
