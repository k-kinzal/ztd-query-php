<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\DiagnosticText;
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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Executes the statements that control replication: START and STOP REPLICA, CHANGE REPLICATION SOURCE and FILTER, RESET BINARY LOGS AND GTIDS and RESET REPLICA, and START and STOP GROUP_REPLICATION.
 *
 * The emulated server starts with an unconfigured default channel, and a
 * CHANGE creates named channels before validating their source options. Each statement but
 * the group replication ones commits the open transaction. START REPLICA requires a configured
 * source host for the receiver, noting first that USER or PASSWORD credentials are sent in plain text;
 * it records the requested local state without opening a network replication connection.
 * SQL_THREAD requires an initialized connection repository before it starts the applier thread,
 * which STOP REPLICA stops and RESET REPLICA refuses to run beside (ER_REPLICA_CHANNEL_MUST_STOP).
 * STOP REPLICA notes threads that are already stopped. CHANGE REPLICATION SOURCE TO initializes
 * the connection repository; RESET closes it. CHANGE REPLICATION FILTER requires
 * configured target channels to have stopped appliers; an explicit channel also needs
 * a source host. MySQL 5.7 checks its default applier even without a host. RESET
 * BINARY LOGS AND GTIDS deletes every binary log file and starts again from the number TO names,
 * 1 by default. Group replication is not configured, and its statements refuse an open
 * transaction or explicit table locks. STOP REPLICA also refuses explicit table locks before
 * looking up channels or checking legacy replica configuration. Modern binary-log resets
 * refuse those locks before changing any logs (verified on MySQL 5.6, 5.7, 8.0 and 8.4).
 * MySQL 5.6 refuses RESET MASTER when log_bin is
 * off (ER_FLUSH_MASTER_BINLOG_CLOSED; verified on a live 5.6.51 server).
 * A successful reset with binary logging enabled contributes one Flush_commands event per statement, even
 * when RESET MASTER is repeated or a later replica reset fails (verified on MySQL 8.0.44).
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        if ($statement instanceof StopReplica && $session->locks !== []) {
            throw StatementError::LockedOrActiveTransaction->error();
        }
        (new LegacyReplication())->check($statement, $session);
        if ($statement instanceof StartGroupReplication || $statement instanceof StopGroupReplication) {
            throw $session->transaction->open || $session->locks !== [] ? StatementError::LockedOrActiveTransaction->error() : AdministrationError::GroupReplicationNotConfigured->error();
        }
        $session->transaction->commit();
        if ($statement instanceof StartReplica) {
            $this->start($statement, $session);
        } elseif ($statement instanceof StopReplica) {
            $this->stop($statement, $session);
        } elseif ($statement instanceof ChangeReplicationFilter) {
            $this->filter($statement, $session);
        } elseif ($statement instanceof Reset) {
            $this->resetTargets($statement, $session);
        } elseif ($statement instanceof ChangeReplicationSource) {
            (new SourceChange())->check($statement, $session, $context);
        }

        return new Completion(0, 0, $session->diagnostics->raised());
    }

    /**
     * Requires stopped appliers for the filter's configured channels.
     * An unqualified modern filter skips channels without a source host.
     *
     * @throws \MySqlMemory\Error\SqlError When a channel is unavailable or its applier runs
     */
    public function filter(ChangeReplicationFilter $statement, Session $session): void
    {
        $registry = $session->instance->registry;
        if ($session->settings()->legacy()) {
            if ($registry->replication->applying) {
                throw AdministrationError::LegacyApplierRunning->error();
            }
            return;
        }
        $channels = array_filter($this->targets(null, $registry), static fn ($state): bool => $state->host !== '');
        if ($statement->channel !== null) {
            $name = strtolower((new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->value($statement->channel));
            if (in_array($name, ['group_replication_applier', 'group_replication_recovery'], true)) {
                throw AdministrationError::ReplicaChannelOperation->error('CHANGE REPLICATION FILTER', $name);
            }
            $state = $channels[$name] ?? null;
            if ($state === null) {
                throw AdministrationError::ReplicaNotInitialized->error();
            }
            $channels = [$name => $state];
        }
        foreach ($channels as $name => $state) {
            if ($state->applying) {
                throw AdministrationError::ReplicaApplierRunning->error((string) $name);
            }
        }
    }

    /**
     * Applies the combined RESET options, recording one binary-log table flush.
     * Binary logs reset before the replica, including when a replica target was written first.
     *
     * @throws \MySqlMemory\Error\SqlError When binary logging is required or a replica target cannot reset
     */
    public function resetTargets(Reset $statement, Session $session): void
    {
        $registry = $session->instance->registry;
        foreach ($statement->effectiveTargets() as $target) {
            if ($target instanceof ResetBinaryLogs && !$session->settings()->legacy() && $session->locks !== []) {
                throw StatementError::LockedOrActiveTransaction->error();
            }
            if ($target instanceof ResetBinaryLogs && $session->settings()->release() === GrammarRelease::MySql5651 && !\MySqlMemory\Registry\BinaryLog::enabled($session)) {
                throw AdministrationError::BinlogClosed->error('RESET MASTER');
            }
            $this->reset($target, $registry, $session->settings()->legacy());
            if ($target instanceof ResetBinaryLogs && \MySqlMemory\Registry\BinaryLog::enabled($session)) {
                $registry->status->add('Flush_commands');
            }
            if ($target instanceof ResetBinaryLogs && $session->settings()->release() !== GrammarRelease::MySql5651) {
                $session->instance->dictionary->cache->open('mysql', 'gtid_executed');
            }
        }
    }

    /**
     * Starts the requested replica threads, warning about supplied credentials before refusing an unconfigured receiver.
     */
    public function start(StartReplica $statement, Session $session): void
    {
        $channels = $this->threads($statement->channel, $session->instance->registry, $session->settings()->legacy());
        foreach ($channels as $name => $replication) {
            $this->startChannel($statement, $session, $replication, (string) $name);
        }
    }

    /**
     * Starts one selected channel without changing a sibling's repository or thread state.
     */
    public function startChannel(StartReplica $statement, Session $session, \MySqlMemory\Registry\Replication $replication, string $name): void
    {
        if ($statement->user !== null || $statement->password !== null) {
            $session->diagnostics->note(AccountError::InsecurePlainText, AccountError::InsecurePlainText->message());
        }
        if (($session->settings()->legacy() && (int) $session->variables->read('server_id') === 0) || ($session->settings()->release() === GrammarRelease::MySql5651 && $replication->host === '')) {
            throw AdministrationError::ReplicaNotConfigured->error(LegacyReplication::CANNOT_START);
        }
        $receiver = $statement->threads === [] || array_filter($statement->threads, static fn (ReplicaThread $thread): bool => $thread !== ReplicaThread::Applier) !== [];
        $applier = $statement->threads === [] || in_array(ReplicaThread::Applier, $statement->threads, true);
        if ($receiver) {
            $replication->initialized = true;
            if ($replication->host === '') {
                throw AdministrationError::ReplicaNotConfigured->error($session->settings()->legacy() ? LegacyReplication::CANNOT_START : AdministrationError::ReplicaNotConfigured->message());
            }
        }
        if (!$applier || $replication->applying) {
            if ($statement->until !== null && $receiver) {
                $session->diagnostics->note(AdministrationError::ReplicaUntilIgnored, AdministrationError::ReplicaUntilIgnored->message());
            } elseif ($applier && $replication->applying) {
                $session->diagnostics->note(AdministrationError::ReplicaThreadsRunning, AdministrationError::ReplicaThreadsRunning->message($name));
            }
            return;
        }
        (new UntilExecution())->check($statement->until, $session);
        if (!$replication->initialized) {
            throw AdministrationError::ReplicaConnectionMetadata->error();
        }
        $replication->applying = !$replication->missing && !(new UntilExecution())->complete($statement->until, $session);
    }

    /**
     * Resets the binary log or the replica.
     *
     * @throws \MySqlMemory\Error\SqlError When the channel does not exist or its applier thread runs
     */
    public function reset(ResetTarget $target, Registry $registry, bool $legacy = false): void
    {
        if ($target instanceof ResetBinaryLogs) {
            $registry->binaryLog->reset($target->first === null ? 1 : (int) (new Literals())->number($target->first));
        }
        if ($target instanceof ResetReplica) {
            foreach ($this->targets($target->channel, $registry, $legacy) as $name => $state) {
                if ($state->applying) {
                    throw AdministrationError::ReplicaChannelRunning->error((string) $name);
                }
                $state->reset($target->all);
                if ($target->all && $name !== '') {
                    unset($registry->channels[$name]);
                }
                if (!$legacy && !$target->all) {
                    (new SourceRepository())->check($state, true);
                }
            }
        }
    }

    /**
     * Resolves an existing channel by its lower-case name and rejects a line feed.
     *
     * @throws \MySqlMemory\Error\SqlError When the named channel does not exist
     */
    public function channel(?Text $channel, bool $legacy = false, ?Registry $registry = null): string
    {
        if ($channel === null) {
            return '';
        }
        $name = (new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->value($channel);
        if (str_contains($name, "\n")) {
            throw DataError::WrongValue->error('argument contains not-allowed LF', (new DiagnosticText())->bytes($name, 128, $legacy));
        }
        $name = strtolower($name);
        if ($name !== '' && !isset($registry?->channels[$name])) {
            throw $legacy ? new \MySqlMemory\Error\SqlError(AdministrationError::ReplicaChannelMissing, "Slave channel '" . $name . "' does not exist.") : AdministrationError::ReplicaChannelMissing->error($name);
        }
        return $name;
    }

    /**
     * Selects the requested channel or every channel for an unqualified thread/reset command.
     *
     * @return array<array-key, \MySqlMemory\Registry\Replication> Channels in name order; PHP stores numeric names as integer keys
     */
    public function targets(?Text $channel, Registry $registry, bool $legacy = false): array
    {
        if ($channel === null) {
            $channels = ['' => $registry->replication] + $registry->channels;
            ksort($channels, SORT_STRING);
            return $channels;
        }
        $name = $this->channel($channel, $legacy, $registry);
        return [$name => $name === '' ? $registry->replication : $registry->channels[$name]];
    }

    /**
     * Selects configured hosts for a multi-channel thread command, while an explicit channel can run a local applier without a receiver host.
     *
     * @return array<array-key, \MySqlMemory\Registry\Replication> Selected channel identities without renumbering
     */
    public function threads(?Text $channel, Registry $registry, bool $legacy = false): array
    {
        $channels = $this->targets($channel, $registry, $legacy);
        return $channel === null && count($channels) > 1 ? array_filter($channels, static fn ($state): bool => $state->host !== '') : $channels;
    }

    /**
     * Stops the selected appliers, suppressing individual already-stopped notes for a multi-channel command.
     */
    public function stop(StopReplica $statement, Session $session): void
    {
        $channels = $this->threads($statement->channel, $session->instance->registry, $session->settings()->legacy());
        foreach ($channels as $name => $state) {
            if ($state->applying && ($statement->threads === [] || in_array(ReplicaThread::Applier, $statement->threads, true))) {
                $state->applying = false;
            } elseif ($statement->channel !== null || $session->instance->registry->channels === []) {
                $notice = $session->settings()->release() === GrammarRelease::MySql5651 ? AdministrationError::LegacyReplicaStopped : AdministrationError::ReplicaThreadsStopped;
                $session->diagnostics->note($notice, $notice === AdministrationError::LegacyReplicaStopped ? $notice->message() : $notice->message((string) $name));
            }
        }
    }

}
