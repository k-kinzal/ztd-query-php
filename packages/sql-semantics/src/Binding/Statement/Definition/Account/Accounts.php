<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads account identities, credential literals, and unsigned counts from MySQL account grammar nodes.
 * @visibility SqlSemantics
 */
final class Accounts
{
    /**
     * The authenticated account keyword stays symbolic; a quoted CURRENT_USER is an ordinary name.
     */
    public static function account(Node $user, Identifiers $identifiers): AccountName|CurrentAccount
    {
        $tokens = $user->tokens();
        if ($tokens[0]->name === 'CURRENT_USER') {
            return CurrentAccount::Authenticated;
        }
        return new AccountName(AccountNames::part($tokens[0], $identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $identifiers) : null);
    }

    /**
     * USER() names the connecting client account; any other target is a named or authenticated account.
     */
    public static function target(Node $node, Identifiers $identifiers): AccountName|CurrentAccount|ClientAccount
    {
        return $node->name === 'user_func' ? ClientAccount::Connected : self::account($node, $identifiers);
    }

    /**
     * @return non-empty-list<AccountName|CurrentAccount>
     * @throws UnclassifiedSql
     */
    public static function list(Node $list, Identifiers $identifiers): array
    {
        $accounts = array_map(static fn (Node $user): AccountName|CurrentAccount => self::account($user, $identifiers), Tree::outer($list, ['user']));
        if ($accounts === []) {
            throw new UnclassifiedSql('An account list requires at least one account.');
        }
        return Collections::nonEmpty($accounts);
    }

    /**
     * A credential or metadata literal is kept with its spelling and never interpreted.
     * @throws UnclassifiedSql
     */
    public static function literal(Token $token): Literal
    {
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        if (!$literal instanceof Literal) {
            throw new UnclassifiedSql('Unclassified account literal: ' . $token->text);
        }
        return $literal;
    }

    /**
     * Reads a decimal or hexadecimal unsigned count; fractional or overflowing spellings are impossible values.
     * @throws InvalidSql
     */
    public static function count(Token $token, Node $context): int
    {
        $text = $token->text;
        if (preg_match('/^0[xX]([0-9a-fA-F]+)$/', $text, $hex) === 1 || preg_match('/^[xX]\'([0-9a-fA-F]*)\'$/', $text, $hex) === 1) {
            $value = hexdec($hex[1]);
            return is_int($value) ? $value : throw new InvalidSql(InputViolation::AccountLimit, $context);
        }
        $digits = ltrim($text, '0');
        if (preg_match('/^[0-9]+$/', $text) !== 1 || ($digits !== '' && (string) (int) $digits !== $digits)) {
            throw new InvalidSql(InputViolation::AccountLimit, $context);
        }
        return (int) $digits;
    }
}
