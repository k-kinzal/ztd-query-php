<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolePolicyStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds every ALTER USER form: per-account changes, USER() credentials, default roles, and factor registration.
 * @visibility SqlSemantics
 */
final class UserAlterations
{
    /**
     * Dispatches on the node following ALTER USER [IF EXISTS].
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, Identifiers $identifiers): BoundStatement
    {
        $ifExists = array_filter(Tree::outer($statement, ['if_exists']), Tree::hasTokens(...)) !== [];
        $children = array_values(array_filter(Tree::significant($statement), static fn (Node|Token $child): bool => $child instanceof Node && $child->name !== 'alter_user_command'));
        $first = $children[0] ?? throw new UnclassifiedSql('ALTER USER requires its target.');
        if ($first->name === 'alter_user_list' && Tree::outer($first, ['alter_user']) === []) {
            return self::expiry($origin, $first, $identifiers);
        }
        if (in_array($first->name, ['alter_user_list', 'grant_list'], true)) {
            $alterations = array_map(static fn (Node $change): CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval => $change->name === 'alter_user' ? self::alteration($change, $identifiers) : self::legacy($change, $identifiers), Tree::outer($first, ['alter_user', 'grant_user']));
            return new AlterUsersStatement($origin, Collections::nonEmpty($alterations), $ifExists, AccountClauses::requirement($statement), AccountClauses::limits($statement), AccountClauses::policies($statement), AccountClauses::annotation($statement));
        }
        $registration = Tree::child($statement, ['opt_user_registration']);
        if ($registration !== null) {
            return FactorRegistrations::bind($origin, $registration, Accounts::target($first, $identifiers));
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $statement->tokens());
        if (in_array('ROLE', $words, true) && ($children[1] ?? null)?->name === 'role_list') {
            return new AlterDefaultRolesStatement($origin, Accounts::account($first, $identifiers), AccountNames::read($children[1], $identifiers));
        }
        if (in_array('ROLE', $words, true)) {
            return new AlterDefaultRolePolicyStatement($origin, Accounts::account($first, $identifiers), DefaultRolePolicy::from($words[count($words) - 1]));
        }
        return new AlterUsersStatement($origin, [self::client($statement, $children, $identifiers)], $ifExists);
    }

    /**
     * MySQL 5.6 expires each listed account's password; the form is one shared policy over plain targets.
     * @throws UnclassifiedSql
     */
    public static function expiry(Origin $origin, Node $list, Identifiers $identifiers): AlterUsersStatement
    {
        $targets = array_map(static fn (Node $user): AccountTarget => new AccountTarget(Accounts::account($user, $identifiers)), Tree::outer($list, ['user']));
        if ($targets === []) {
            throw new UnclassifiedSql('Password expiry requires at least one account.');
        }
        return new AlterUsersStatement($origin, Collections::nonEmpty($targets), policies: [AccountPolicy::ExpirePassword]);
    }

    /**
     * USER() forms: a supplied or generated password with optional replacement and retention, or a discard.
     * @param list<Node> $children The nodes following the command
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function client(Node $statement, array $children, Identifiers $identifiers): CredentialChange|OldPasswordDiscard
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $statement->tokens());
        if (in_array('DISCARD', $words, true)) {
            return new OldPasswordDiscard(ClientAccount::Connected);
        }
        $form = $children[1] ?? null;
        $tokens = $statement->tokens();
        if ($form === null) {
            return new CredentialChange(ClientAccount::Connected, new PasswordIdentification(Accounts::literal($tokens[count($tokens) - 1])));
        }
        $identification = Identifications::read($form, $identifiers);
        if (!$identification instanceof PasswordIdentification && $identification !== RandomPassword::Generated) {
            throw new UnclassifiedSql('Unclassified client credential form: ' . Tree::text($statement));
        }
        $replace = Tree::child($statement, ['opt_replace_password']);
        return new CredentialChange(ClientAccount::Connected, $identification, $replace === null ? null : Accounts::literal($replace->tokens()[1]), Tree::child($statement, ['opt_retain_current_password']) !== null);
    }

    /**
     * One MySQL 8 alter_user node: a credential form, a discard, a factor change, or a bare account.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function alteration(Node $change, Identifiers $identifiers): CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval
    {
        $children = Tree::significant($change);
        $user = $children[0] ?? null;
        if (!$user instanceof Node) {
            throw new UnclassifiedSql('An account alteration requires its account.');
        }
        $account = Accounts::account($user, $identifiers);
        $form = $children[1] ?? null;
        if ($form === null) {
            return new AccountTarget($account);
        }
        if ($form instanceof Token) {
            return FactorRegistrations::change($change, $account, strtoupper($form->text), $identifiers);
        }
        if ($form->name === 'opt_discard_old_password') {
            return new OldPasswordDiscard($account);
        }
        $replace = Tree::child($change, ['TEXT_STRING_password']);
        return self::classify($account, Identifications::read($form, $identifiers), $replace === null ? null : Accounts::literal($replace->tokens()[0]), Tree::child($change, ['opt_retain_current_password']) !== null);
    }

    /**
     * MySQL 5.7 grant_user forms change one credential without replacement or retention.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function legacy(Node $grantUser, Identifiers $identifiers): CredentialChange|AuthenticationChange|PluginChange|AccountTarget
    {
        $definition = UserDefinitions::legacy($grantUser, $identifiers);
        return $definition->identification === null ? new AccountTarget($definition->account) : self::classify($definition->account, $definition->identification, null, false);
    }

    /**
     * Cleartext and generated passwords accept replacement; plugin-only and encoded forms do not.
     */
    public static function classify(AccountName|CurrentAccount $account, PasswordIdentification|HashIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification $identification, ?Literal $replace, bool $retain): CredentialChange|AuthenticationChange|PluginChange
    {
        if ($identification instanceof PasswordIdentification || $identification instanceof PluginPasswordIdentification || $identification === RandomPassword::Generated) {
            return new CredentialChange($account, $identification, $replace, $retain);
        }
        if ($identification instanceof PluginIdentification) {
            return new PluginChange($account, $identification->plugin);
        }
        return new AuthenticationChange($account, $identification, $retain);
    }
}
