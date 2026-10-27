<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\CreateText;
use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser;

/**
 * Executes SHOW CREATE USER.
 *
 * The statement writes the CREATE USER statement of an account (see CreateText), in one text
 * column of 256 characters named after the account, encoded in character_set_results.
 * With no result conversion, the metadata uses latin1. MySQL 5.7 writes the
 * statement in its own form. An account that does not exist is ER_CANNOT_USER (verified on live
 * 8.4 and 5.7.44 servers).
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof ShowCreateUser);
        $names = new Names($session->settings()->release());
        $names->check([$statement->user]);
        $identity = $names->identity($statement->user, $session);
        $accounts = $session->instance->accounts;
        $account = $accounts->find($identity);
        if ($account === null) {
            throw (new Names($session->settings()->release()))->failure('SHOW CREATE USER', $identity->quoted());
        }
        if (!$session->settings()->legacy()) {
            $names->ascii($identity, 1, $context->diagnostics);
        }
        $legacy = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $results = $session->variables->read('character_set_results');
        $charset = (is_string($results) ? \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($results) : null) ?? \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1');
        $collation = $session->variables->resultCollation() ?? $charset->defaultCollation($session->settings()->release());
        $column = new ResultColumn('CREATE USER for ' . $identity->text(), Field::VarString, 256 * $charset->maxLength, 31, ColumnFlag::NotNull->value, $collation->id);
        $text = (new CreateText())->statement($account, array_values($accounts->defaults[$identity->key()] ?? []), $legacy);

        return new ResultSet([$column], [[$text]], $context->diagnostics->raised());
    }
}
