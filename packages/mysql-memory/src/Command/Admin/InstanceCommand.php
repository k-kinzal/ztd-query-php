<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadKeyring;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey;
use SqlSemantics\Statement\Operation;

/**
 * Executes ALTER INSTANCE, LOCK INSTANCE FOR BACKUP and UNLOCK INSTANCE.
 *
 * ALTER INSTANCE commits the open transaction. The emulated server has no keyring and does not
 * encrypt its binary log: ROTATE INNODB MASTER KEY finds no master key, ROTATE BINLOG MASTER KEY
 * finds binary log encryption off, and RELOAD KEYRING fails. RELOAD TLS succeeds for the main and
 * the administrative connection interfaces, mysql_main and mysql_admin, and is a syntax error
 * for another channel; ENABLE and DISABLE INNODB REDO_LOG succeed. The emulated server runs no
 * concurrent DDL, so the backup lock changes nothing (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html,
 * https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 *
 * @visibility MySqlMemory
 */
final class InstanceCommand implements Command
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
     * Carries out the instance action, or refuses it as the server does.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if (!$statement instanceof AlterInstance) {
            return new Completion();
        }
        $session->transaction->commit();
        $action = $statement->action;
        if ($action instanceof ReloadTls && $action->channel !== null && !in_array(strtolower($action->channel->value), ['mysql_main', 'mysql_admin'], true)) {
            throw ErrorCode::SyntaxError->error();
        }
        if ($action instanceof RotateMasterKey) {
            throw strtoupper($action->keyring->value) === 'BINLOG' ? ErrorCode::BinlogEncryptionOff->error() : ErrorCode::KeyringMissing->error();
        }
        if ($action instanceof ReloadKeyring) {
            throw ErrorCode::KeyringReloadFailed->error();
        }

        return new Completion();
    }
}
