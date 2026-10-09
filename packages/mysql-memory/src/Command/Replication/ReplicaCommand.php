<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
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
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;
use SqlSemantics\Statement\Node;
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
 * transaction (verified on a live 8.4 server). MySQL 5.6 refuses RESET MASTER when log_bin is
 * off (ER_FLUSH_MASTER_BINLOG_CLOSED; verified on a live 5.6.51 server).
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
        $this->deprecated($statement, $session, $context);
        (new LegacyReplication())->check($statement, $session);
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
                if ($target instanceof ResetBinaryLogs && $session->settings()->release() === GrammarRelease::MySql5651 && !\MySqlMemory\Registry\BinaryLog::enabled($session)) {
                    throw AdministrationError::BinlogClosed->error('RESET MASTER');
                }
                $this->reset($target, $registry);
            }
        } elseif ($statement instanceof ChangeReplicationSource) {
            (new SourceChange())->check($statement, $session, $context);
        }

        return new Completion(0, 0, $session->diagnostics->count());
    }

    /**
     * Warns that a statement written in the legacy vocabulary of replication is deprecated, naming the statement and then each option spelled with MASTER (ER_WARN_DEPRECATED_SYNTAX).
     *
     * MySQL 8.0 deprecated START SLAVE, STOP SLAVE, RESET SLAVE, SHOW SLAVE STATUS, SHOW SLAVE
     * HOSTS and CHANGE MASTER TO with its MASTER_ options, which MySQL 8.4 removed; MySQL 5.6 and
     * 5.7 know no other vocabulary and do not warn (verified on live 5.7 and 8.0 servers).
     * Source: https://dev.mysql.com/doc/refman/8.0/en/replication-statements.html,
     * https://dev.mysql.com/doc/relnotes/mysql/8.4/en/news-8-4-0.html.
     */
    public function deprecated(Node $statement, Session $session, Context $context): void
    {
        $release = $session->settings()->release();
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            return;
        }
        $legacy = static fn (Terminology $terminology): bool => $terminology === Terminology::Legacy;
        $replaced = match (true) {
            $statement instanceof StartReplica && $legacy($statement->terminology) => ['START SLAVE', 'START REPLICA'],
            $statement instanceof StopReplica && $legacy($statement->terminology) => ['STOP SLAVE', 'STOP REPLICA'],
            $statement instanceof ShowReplicaStatus && $legacy($statement->terminology) => ['SHOW SLAVE STATUS', 'SHOW REPLICA STATUS'],
            $statement instanceof ShowReplicas && $legacy($statement->terminology) => ['SHOW SLAVE HOSTS', 'SHOW REPLICAS'],
            $statement instanceof ChangeReplicationSource && $statement->synonym => ['CHANGE MASTER', 'CHANGE REPLICATION SOURCE'],
            $statement instanceof Reset && array_filter($statement->targets, static fn (ResetTarget $target): bool => $target instanceof ResetReplica && $target->synonym) !== [] => ['RESET SLAVE', 'RESET REPLICA'],
            default => null,
        };
        if ($replaced === null) {
            return;
        }
        $context->warning(StatementError::DeprecatedSyntax, ...$replaced);
        if ($statement instanceof ChangeReplicationSource) {
            foreach ($statement->options as $option) {
                if ($option->synonym) {
                    $context->warning(StatementError::DeprecatedSyntax, $option->kind->keyword(Terminology::Legacy), $option->kind->value);
                }
            }
        }
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
