<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\CreateText;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW CREATE USER.
 *
 * The statement writes the CREATE USER statement of an account (see CreateText), in one text
 * column of 256 characters named after the account. An account that does not exist is
 * ER_CANNOT_USER (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-user.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCreateUserCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Writes the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowCreateUser);
        $names = new Names();
        $names->check([$statement->user]);
        $identity = $names->identity($statement->user, $session);
        $accounts = $session->instance->accounts;
        $account = $accounts->find($identity);
        if ($account === null) {
            throw ErrorCode::CannotUser->error('SHOW CREATE USER', $identity->quoted());
        }
        $column = new ResultColumn('CREATE USER for ' . $identity->text(), Field::VarString, 1024, 31, ColumnFlag::NotNull->value, 255);
        $text = (new CreateText())->statement($account, array_values($accounts->defaults[$identity->key()] ?? []));

        return new ResultSet([$column], [[$text]], $context->diagnostics->count());
    }
}
