<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Account\AccountRename;
use SqlSemantics\Model\Statement\Definition\MySql\Account\RenameUsersStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Routes MySQL account and privilege administration: users, roles, renames, grants, and revocations.
 * @visibility SqlSemantics
 */
final class AccountCommands
{
    /**
     * Returns null for other dialects and for CREATE, ALTER, and RENAME forms outside account administration.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        $tokens = $statement->tokens();
        $verb = strtoupper($tokens[0]->text ?? '');
        $object = strtoupper($tokens[1]->text ?? '');
        $identifiers = $context->tables->identifiers;
        return match (true) {
            $statement->name === 'create_role_stmt' => UserDefinitions::roles($origin, $statement, $identifiers),
            $statement->name === 'alter_user_stmt', $verb === 'ALTER' && $object === 'USER' => UserAlterations::bind($origin, $statement, $identifiers),
            $verb === 'CREATE' && $object === 'USER' => UserDefinitions::bind($origin, $statement, $identifiers),
            $verb === 'RENAME' && $object === 'USER' => self::rename($origin, $statement, $identifiers),
            $verb === 'GRANT' => PrivilegeGrants::bind($origin, $statement, $context),
            $verb === 'REVOKE' => PrivilegeRevocations::bind($origin, $statement, $context),
            default => null,
        };
    }

    /**
     * RENAME USER pairs consecutive accounts of its list.
     * @throws UnclassifiedSql
     */
    public static function rename(Origin $origin, Node $statement, Identifiers $identifiers): RenameUsersStatement
    {
        $users = Tree::outer($statement, ['user']);
        if ($users === [] || count($users) % 2 !== 0) {
            throw new UnclassifiedSql('RENAME USER requires account pairs.');
        }
        $renames = [];
        foreach (array_chunk($users, 2) as [$from, $to]) {
            $renames[] = new AccountRename(Accounts::account($from, $identifiers), Accounts::account($to, $identifiers));
        }
        return new RenameUsersStatement($origin, Collections::nonEmpty($renames));
    }
}
