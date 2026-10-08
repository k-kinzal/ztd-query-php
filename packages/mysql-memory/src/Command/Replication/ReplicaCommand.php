<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StopGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetTarget;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Statement\Operation;

/**
 * Executes the statements that control replication: START and STOP REPLICA, CHANGE REPLICATION SOURCE and FILTER, RESET BINARY LOGS AND GTIDS and RESET REPLICA, and START and STOP GROUP_REPLICATION.
 *
 * The emulated server is no replica: its only channel is the default one, unconfigured, and a
 * statement naming another channel fails (ER_REPLICA_CHANNEL_DOES_NOT_EXIST). Each statement but
 * the group replication ones commits the open transaction. START REPLICA cannot start the
 * receiver thread (ER_REPLICA_CONFIGURATION), noting first that a PASSWORD is sent in plain text;
 * SQL_THREAD alone starts the applier thread, which STOP REPLICA stops and RESET REPLICA refuses
 * to run beside (ER_REPLICA_CHANNEL_MUST_STOP). STOP REPLICA notes threads that are already
 * stopped. CHANGE REPLICATION SOURCE TO is accepted and kept nowhere; CHANGE REPLICATION FILTER
 * for a channel fails, the replica not being initialized (ER_REPLICA_CONFIGURATION). RESET
 * BINARY LOGS AND GTIDS deletes every binary log file and starts again from the number TO names,
 * 1 by default. Group replication is not configured, and its statements refuse an open
 * transaction (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stop-replica.html,
 * https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-replica.html,
 * https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 *
 * @visibility MySqlMemory
 */
final class ReplicaCommand implements Command
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
     * Controls the replication of the server, as far as the emulated server has any.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $registry = $session->instance->registry;
        if ($statement instanceof StartGroupReplication || $statement instanceof StopGroupReplication) {
            throw $session->transaction->open ? StatementError::LockedOrActiveTransaction->error() : AdministrationError::GroupReplicationNotConfigured->error();
        }
        $session->transaction->commit();
        if ($statement instanceof StartReplica) {
            $this->channel($statement->channel);
            if ($statement->password !== null) {
                $session->diagnostics->note(AccountError::InsecurePlainText, AccountError::InsecurePlainText->message());
            }
            if ($statement->threads === [] || in_array(ReplicaThread::Receiver, $statement->threads, true)) {
                throw AdministrationError::ReplicaNotConfigured->error();
            }
            $registry->applying = true;
        } elseif ($statement instanceof StopReplica) {
            $this->channel($statement->channel);
            if ($registry->applying && ($statement->threads === [] || in_array(ReplicaThread::Applier, $statement->threads, true))) {
                $registry->applying = false;
            } else {
                $session->diagnostics->note(AdministrationError::ReplicaThreadsStopped, AdministrationError::ReplicaThreadsStopped->message(''));
            }
        } elseif ($statement instanceof ChangeReplicationFilter) {
            if ($statement->channel !== null) {
                throw AdministrationError::ReplicaNotInitialized->error();
            }
        } elseif ($statement instanceof Reset) {
            foreach ($statement->targets as $target) {
                $this->reset($target, $registry);
            }
        } elseif ($statement instanceof ChangeReplicationSource) {
            return new Completion();
        }

        return new Completion(0, 0, $session->diagnostics->count());
    }

    /**
     * Resets the binary log or the replica.
     *
     * @throws \MySqlMemory\Error\SqlError When the channel does not exist or its applier thread runs
     */
    public function reset(ResetTarget $target, Registry $registry): void
    {
        if ($target instanceof ResetBinaryLogs) {
            $registry->binaryLog->reset($target->first === null ? 1 : (int) (new Literals())->number($target->first));
        }
        if ($target instanceof ResetReplica) {
            $this->channel($target->channel);
            if ($registry->applying) {
                throw AdministrationError::ReplicaChannelRunning->error('');
            }
        }
    }

    /**
     * Refuses a channel other than the default one, naming it in lower case, and a channel name with a line feed.
     *
     * @throws \MySqlMemory\Error\SqlError When the channel is not the default one
     */
    public function channel(?Text $channel): void
    {
        if ($channel === null) {
            return;
        }
        $name = (new Literals())->bytes($channel);
        if (str_contains($name, "\n")) {
            throw DataError::WrongValue->error('argument contains not-allowed LF', $name);
        }
        if ($name !== '') {
            throw AdministrationError::ReplicaChannelMissing->error(strtolower($name));
        }
    }
}
