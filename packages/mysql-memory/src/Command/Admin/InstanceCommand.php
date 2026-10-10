<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
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
 * Executes ALTER INSTANCE, instance locks, CLONE, SHUTDOWN and RESTART.
 *
 * ALTER INSTANCE commits the open transaction. The emulated server has no keyring and does not
 * encrypt its binary log: ROTATE INNODB MASTER KEY finds no master key, ROTATE BINLOG MASTER KEY
 * finds binary log encryption off, and RELOAD KEYRING fails. RELOAD TLS succeeds for the main and
 * the administrative connection interfaces, mysql_main and mysql_admin, and is a syntax error
 * for another channel; ENABLE and DISABLE INNODB REDO_LOG succeed. The emulated server runs no
 * concurrent DDL, so the backup lock changes nothing (verified on a live 8.4 server).
 * CLONE requires the absent clone plugin. SHUTDOWN ends all sessions; RESTART also reloads the
 * startup configuration, retaining durable table contents, when the instance has a supervisor.
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
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown) {
            $session->instance->shutdown();

            return new Completion();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart) {
            $session->instance->restart();

            return new Completion();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance) {
            throw AdministrationError::PluginIsNotLoaded->error('clone');
        }
        if (!$statement instanceof AlterInstance) {
            return new Completion();
        }
        $session->transaction->commit();
        $action = $statement->action;
        if ($action instanceof ReloadTls && $action->channel !== null && !in_array(strtolower($action->channel->value), ['mysql_main', 'mysql_admin'], true)) {
            throw StatementError::SyntaxError->error();
        }
        if ($action instanceof RotateMasterKey) {
            throw strtoupper($action->keyring->value) === 'BINLOG' ? AdministrationError::BinlogEncryptionOff->error() : AdministrationError::KeyringMissing->error();
        }
        if ($action instanceof ReloadKeyring) {
            throw AdministrationError::KeyringReloadFailed->error();
        }

        return new Completion();
    }
}
