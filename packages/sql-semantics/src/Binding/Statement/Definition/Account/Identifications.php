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
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads IDENTIFIED clauses in their MySQL 8 rule forms and their MySQL 5.6/5.7 token forms.
 * @visibility SqlSemantics
 */
final class Identifications
{
    /**
     * Each MySQL 8 identification rule maps to exactly one credential form.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Node $node, Identifiers $identifiers): PasswordIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification
    {
        if ($node->name === 'identification') {
            $inner = Tree::significant($node)[0] ?? null;
            if (!$inner instanceof Node) {
                throw new UnclassifiedSql('An identification requires its credential form.');
            }
            return self::read($inner, $identifiers);
        }
        $tokens = $node->tokens();
        $last = $tokens[count($tokens) - 1];
        try {
            return match ($node->name) {
                'identified_by_password' => new PasswordIdentification(Accounts::literal($last)),
                'identified_by_random_password' => RandomPassword::Generated,
                'identified_with_plugin' => new PluginIdentification(self::plugin($node, $identifiers)),
                'identified_with_plugin_as_auth' => new PluginHashIdentification(self::plugin($node, $identifiers), Accounts::literal($last)),
                'identified_with_plugin_by_password' => new PluginPasswordIdentification(self::plugin($node, $identifiers), Accounts::literal($last)),
                'identified_with_plugin_by_random_password' => new PluginRandomPasswordIdentification(self::plugin($node, $identifiers)),
                default => throw new UnclassifiedSql('Unclassified identification: ' . Tree::text($node)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::AuthenticationPlugin, $node, $error);
        }
    }

    /**
     * The MySQL 5.6/5.7 grant_user forms: BY, BY PASSWORD, WITH, WITH AS, and WITH BY.
     * @param list<Token> $tokens The tokens following the account name
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function legacy(Node $grantUser, array $tokens, Identifiers $identifiers): PasswordIdentification|HashIdentification|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|null
    {
        if ($tokens === []) {
            return null;
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        $last = $tokens[count($tokens) - 1];
        try {
            if (($words[1] ?? '') === 'BY') {
                return ($words[2] ?? '') === 'PASSWORD' ? new HashIdentification(Accounts::literal($last)) : new PasswordIdentification(Accounts::literal($last));
            }
            $plugin = self::plugin($grantUser, $identifiers);
            return match ($words[3] ?? '') {
                '' => new PluginIdentification($plugin),
                'AS' => new PluginHashIdentification($plugin, Accounts::literal($last)),
                'BY' => new PluginPasswordIdentification($plugin, Accounts::literal($last)),
                default => throw new UnclassifiedSql('Unclassified legacy identification: ' . Tree::text($grantUser)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::AuthenticationPlugin, $grantUser, $error);
        }
    }

    /**
     * The plugin name is the immediate ident_or_text child; account names sit inside their own user node.
     * @throws UnclassifiedSql
     */
    public static function plugin(Node $node, Identifiers $identifiers): string
    {
        $name = Tree::child($node, ['ident_or_text']) ?? throw new UnclassifiedSql('An authentication plugin requires its name.');
        return AccountNames::part($name->tokens()[0], $identifiers);
    }
}
