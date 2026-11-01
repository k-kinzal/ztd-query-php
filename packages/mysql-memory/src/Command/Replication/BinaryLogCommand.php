<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Registry\BinaryLog;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Executes PURGE BINARY LOGS and BINLOG.
 *
 * PURGE BINARY LOGS TO deletes the binary log files before the one it names, which the index
 * must hold (ER_UNKNOWN_TARGET_BINLOG). PURGE BINARY LOGS BEFORE converts its moment to a
 * Unix cutoff and deletes older closed files. It warns that the active file is not purged
 * only when the scan reaches that file. The
 * BINLOG statement decodes its base64 text (ER_BASE64_DECODE_ERROR) and reads events in order:
 * fewer than 13 bytes, or an event longer than the text, is a syntax error, and only a format
 * description or row event is allowed (ER_ONLY_FD_AND_RBR_EVENTS_ALLOWED_IN_BINLOG_STATEMENT,
 * naming the type of the event). Format descriptions and row-query annotations
 * establish connection state; table and row application are handled separately.
 * None of the statements commits the open transaction. A server whose
 * log_bin is off purges nothing and warns of no file (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html,
 * https://dev.mysql.com/doc/refman/8.4/en/binlog.html.
 *
 * @visibility MySqlMemory
 */
final class BinaryLogCommand implements Command
{
    /**
     * The names the server gives the binary log event types it refuses in a BINLOG statement, by type code.
     */
    public const EVENTS = [
        2 => 'Query', 3 => 'Stop', 4 => 'Rotate', 5 => 'Intvar', 9 => 'Append_block', 11 => 'Delete_file', 13 => 'RAND', 14 => 'User var',
        16 => 'Xid', 17 => 'Begin_load_query', 18 => 'Execute_load_query', 23 => 'Write_rows_v1', 24 => 'Update_rows_v1', 25 => 'Delete_rows_v1',
        26 => 'Incident', 27 => 'Heartbeat', 28 => 'Ignorable', 33 => 'Gtid', 34 => 'Anonymous_Gtid', 35 => 'Previous_gtids',
        36 => 'Transaction_context', 37 => 'View_change', 38 => 'XA_prepare', 40 => 'Transaction_payload', 42 => 'Gtid_tagged_log_event',
    ];

    /**
     * The type codes of the events a BINLOG statement decodes: the format description, the table map and the row events.
     */
    public const DECODED = [15, 19, 30, 31, 32, 39];

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Purges the files, or refuses the events.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        $log = $session->instance->registry->binaryLog;
        $logging = BinaryLog::enabled($session);
        if ($statement instanceof PurgeLogsTo && $logging) {
            $number = $log->find((new Literals())->bytes($statement->file)) ?? throw AdministrationError::UnknownTargetBinlog->error();
            $log->purge($number);
        } elseif ($statement instanceof PurgeLogsBefore) {
            $planner = new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
            try {
                $moment = $planner->compiler->compile($statement->moment, new Scope());
            } catch (\MySqlMemory\Error\SqlError $error) {
                throw (new PurgeErrors())->prepared($error, $statement);
            }
            $cutoff = $this->moment($moment, $context);
            if ($logging && $log->before($cutoff)) {
                $session->diagnostics->warning(AdministrationError::ActiveLogNotPurged, AdministrationError::ActiveLogNotPurged->message('./' . $log->active()));
            }
        } elseif ($statement instanceof BinlogEvent) {
            $this->event((new Literals())->bytes($statement->events), $session, $context);
        }

        return new Completion(0, 0, $session->diagnostics->stale ? 0 : $session->diagnostics->raised());
    }

    /**
     * Converts the cutoff after scalar control operations have finished, retaining
     * their first error before any datetime conversion warning. Preparation errors
     * never enter this scope. Verified against runtime ESCAPE failures on MySQL.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the cutoff fails
     */
    public function moment(\MySqlMemory\Evaluation\Evaluable $moment, Context $context): int
    {
        $continuing = $context->continueAfterError;
        $previous = $context->deferredError;
        $context->continueAfterError = true;
        $context->deferredError = null;
        $cutoff = 0;
        try {
            try {
                $value = $moment->evaluate(new Frame($context));
                $cutoff = \MySqlMemory\Evaluation\Convert::toEpoch($value, $moment->domain(), $context)[0] ?? 0;
            } catch (\MySqlMemory\Error\SqlError $error) {
                $context->defer($error);
            }
            if ($context->deferredError !== null) {
                throw $context->deferredError;
            }
            return $cutoff;
        } finally {
            $context->continueAfterError = $continuing;
            $context->deferredError = $previous;
        }
    }

    /**
     * Reads events in order, retaining successfully visited format descriptions.
     * A malformed format attempt allocates the connection's default layout before
     * failing. Rows-query annotations require that layout and carry no row changes.
     * Verified through original, corrupted and concatenated events on MySQL 9.1.0.
     */
    public function event(string $text, ?Session $session = null, ?Context $context = null): void
    {
        $format = $session?->binlog->format;
        foreach ((new Binlog\Events())->read($text) as $event) {
            $type = ord($event[4]);
            if ($type === 15) {
                $format ??= new Binlog\Format(19, '', 0);
                if ($session !== null) {
                    $session->binlog->format = $format;
                }
                $format = Binlog\Format::read($event);
                if ($session !== null) {
                    $session->binlog->format = $format;
                }
                continue;
            }
            $row = [19 => 'Table_map', 29 => 'Rows_query', 30 => 'Write_rows', 31 => 'Update_rows', 32 => 'Delete_rows', 39 => 'Update_rows_partial'][$type] ?? null;
            if ($row === null) {
                throw AdministrationError::BinlogEventRefused->error(self::EVENTS[$type] ?? 'Unknown');
            }
            if ($format === null) {
                throw AdministrationError::NoFormatDescriptionEvent->error($row);
            }
            $this->replay($event, $type, $format, $session, $context);
        }
    }

    /**
     * Applies a row-family event after its format and type checks.
     *
     * @throws \MySqlMemory\Error\SqlError When native row application reports an SQL error
     */
    public function replay(string $event, int $type, Binlog\Format $format, ?Session $session, ?Context $context): void
    {
        if ($type === 29) {
            $body = $format->body($event);
            $body->integer(1);
            return;
        }
        \SqlSemantics\Diagnostic\Check::invariant($session !== null && $context !== null, 'Row replay requires its session and statement context.');
        if ($type === 19) {
            $map = Binlog\TableMap::read($event, $format);
            $session->binlog->tables[$map->id] = $map;
            return;
        }
        try {
            (new Binlog\Rows())->apply($event, $type, $format, $session, $context);
        } catch (\MySqlMemory\Error\SqlError $error) {
            if ($error->error === StatementError::UnknownError) {
                throw $error;
            }
            throw new \MySqlMemory\Error\SqlError($error->error, $error->getMessage(), $error, [...$error->following, [1105, 'Unknown error']]);
        }
    }

}
