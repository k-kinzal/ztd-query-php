<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Password;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Account\PasswordDerivation;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Configuration\Password as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds account credentials independently of system-variable assignments and scalar function calls.
 * @visibility SqlSemantics
 */
final class PasswordBinder
{
    /**
     * Recognizes versioned password productions, including mixed MySQL 5.6 option lists.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, Scope $scope): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || $source->name !== 'set') {
            return null;
        }
        $start = Tree::child($source, ['start_option_value_list']);
        if ($start === null) {
            return null;
        }
        $legacy = Tree::outer($start, ['option_value_no_option_type']);
        $passwords = array_values(array_filter($legacy, static fn (Node $node): bool => ($node->tokens()[0]->name ?? '') === 'PASSWORD'));
        if ($passwords !== []) {
            return LegacyPasswordList::bind($origin, $source, $passwords, $scope);
        }
        if (($start->tokens()[0]->name ?? '') !== 'PASSWORD') {
            return null;
        }
        return self::operation($origin, $start, $scope);
    }

    /**
     * Separates supplied, derived, and generated credentials; literal contents remain unevaluated.
     * @throws UnclassifiedSql
     */
    public static function operation(Origin $origin, Node $node, Scope $scope): Statement\SetPasswordStatement|Statement\SetPasswordHashStatement|Statement\SetDerivedPasswordStatement|Statement\SetRandomPasswordStatement
    {
        $account = self::account($node, $scope);
        $current = Tree::child($node, ['opt_replace_password']);
        $current = $current === null ? null : self::literal($current);
        $retain = Tree::child($node, ['opt_retain_current_password']) !== null;
        $value = Tree::child($node, ['TEXT_STRING_password', 'password', 'text_or_password']);
        if ($value === null) {
            if (array_filter($node->tokens(), static fn ($token): bool => $token->name === 'RANDOM_SYM') === []) {
                throw new UnclassifiedSql('Unclassified password source.');
            }
            return new Statement\SetRandomPasswordStatement($origin, $account, $current, $retain);
        }
        $password = self::literal($value);
        $version = $scope->queries?->tables->schema->grammarVersion;
        if ($version === 'mysql-5.6.51') {
            $derivation = PasswordDerivation::tryFrom(strtoupper($value->tokens()[0]->text));
            return $derivation === null ? new Statement\SetPasswordHashStatement($origin, $account, $password) : new Statement\SetDerivedPasswordStatement($origin, $account, $password, $derivation);
        }
        return new Statement\SetPasswordStatement($origin, $account, $password, $current, $retain);
    }

    /**
     * Identifies a named account or the authenticated principal without resolving the latter's value.
     */
    public static function account(Node $node, Scope $scope): AccountName|CurrentAccount
    {
        $user = Tree::child($node, ['user']);
        if ($user === null || $user->tokens()[0]->name === 'CURRENT_USER') {
            return CurrentAccount::Authenticated;
        }
        $tokens = $user->tokens();
        return new AccountName(AccountNames::part($tokens[0], $scope->identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $scope->identifiers) : null);
    }

    /**
     * Reads one credential token, never a username or nested general expression.
     * @throws UnclassifiedSql
     */
    public static function literal(Node $node): Literal
    {
        foreach ($node->tokens() as $token) {
            if ($token->name === 'TEXT_STRING') {
                $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
                if ($literal instanceof Literal) {
                    return $literal;
                }
            }
        }
        throw new UnclassifiedSql('A password production must contain its text credential.');
    }
}
