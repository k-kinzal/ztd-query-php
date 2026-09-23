<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds MySQL named-object and account removal requests with their applicable policies.
 * @visibility SqlSemantics
 */
final class MySqlRemovals
{
    /**
     * Distinguishes account lists, qualified event names, and global server definitions.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $tokens = $source->tokens();
        if ($origin->dialect !== Dialect::MySql || strtoupper($tokens[0]->text ?? '') !== 'DROP') {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        $exists = Tree::child($source, ['if_exists']) !== null;
        $operation = strtoupper($tokens[1]->text ?? '');
        if ($operation === 'ROLE') {
            return new Statement\DropRolesStatement($origin, AccountNames::read($source, $identifiers), $exists);
        }
        if ($operation === 'USER') {
            return new Statement\DropUsersStatement($origin, self::accounts($source, $identifiers), $exists);
        }
        if ($operation === 'RESOURCE' && strtoupper($tokens[2]->text ?? '') === 'GROUP') {
            $name = Tree::child($source, ['ident']) ?? throw new UnclassifiedSql('A resource-group removal requires its name.');
            return new Statement\DropResourceGroupStatement($origin, $identifiers->name($name->tokens()[0]), Tree::child($source, ['opt_force']) !== null);
        }
        if (!in_array($operation, ['DATABASE', 'SCHEMA', 'EVENT', 'SERVER'], true)) {
            return null;
        }
        $name = Tree::child($source, ['sp_name', 'ident', 'ident_or_text']) ?? throw new UnclassifiedSql('A named removal requires its target.');
        return match ($operation) {
            'DATABASE', 'SCHEMA' => new Statement\DropDatabaseStatement($origin, $identifiers->name($name->tokens()[0]), $exists),
            'EVENT' => new Statement\DropEventStatement($origin, new QualifiedName($identifiers->parts($name)), $exists),
            'SERVER' => new Statement\DropServerStatement($origin, AccountNames::part($name->tokens()[0], $identifiers), $exists),
        };
    }

    /**
     * Retains the authenticated principal as a symbolic reference, without reading its username.
     * @return non-empty-list<AccountName|CurrentAccount>
     */
    public static function accounts(Node $source, Identifiers $identifiers): array
    {
        $accounts = [];
        foreach (Tree::outer($source, ['user']) as $user) {
            $tokens = $user->tokens();
            $accounts[] = $tokens[0]->name === 'CURRENT_USER' ? CurrentAccount::Authenticated : new AccountName(AccountNames::part($tokens[0], $identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $identifiers) : null);
        }
        return Collections::nonEmpty($accounts);
    }
}
