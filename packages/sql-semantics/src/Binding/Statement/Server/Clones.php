<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Administration\CloneEncryption;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;

/**
 * Binds CLONE INSTANCE FROM; CLONE LOCAL belongs to the session binder.
 * @visibility SqlSemantics
 */
final class Clones
{
    /**
     * Returns null for CLONE LOCAL.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?CloneRemoteStatement
    {
        if (strtoupper($node->tokens()[1]->text ?? '') !== 'INSTANCE') {
            return null;
        }
        $user = Tree::child($node, ['user']) ?? throw new UnclassifiedSql('CLONE INSTANCE requires a donor account.');
        $port = Tree::child($node, ['ulong_num']) ?? throw new UnclassifiedSql('CLONE INSTANCE requires a donor port.');
        $password = Tree::child($node, ['TEXT_STRING_sys']) ?? throw new UnclassifiedSql('CLONE INSTANCE requires a donor password.');
        $directory = Tree::outer($node, ['TEXT_STRING_filesystem'])[0] ?? null;
        $ssl = Tree::outer($node, ['opt_ssl'])[0] ?? null;
        $encryption = $ssl === null || !Tree::hasTokens($ssl) ? null : (count($ssl->tokens()) === 3 ? CloneEncryption::Refused : CloneEncryption::Required);
        return new CloneRemoteStatement($origin, self::donor($user, $context), Literals::text($port), Literals::text($password), $directory === null ? null : Literals::text($directory), $encryption);
    }

    /**
     * Reads a named account or CURRENT_USER without resolving the latter.
     */
    public static function donor(Node $user, QueryContext $context): AccountName|CurrentAccount
    {
        $tokens = $user->tokens();
        if (strtoupper($tokens[0]->text) === 'CURRENT_USER') {
            return CurrentAccount::Authenticated;
        }
        return new AccountName(AccountNames::part($tokens[0], $context->tables->identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $context->tables->identifiers) : null);
    }
}
