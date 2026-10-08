<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Operation;

/**
 * Executes PURGE BINARY LOGS and BINLOG.
 *
 * PURGE BINARY LOGS TO deletes the binary log files before the one it names, which the index
 * must hold (ER_UNKNOWN_TARGET_BINLOG). PURGE BINARY LOGS BEFORE converts its moment to a
 * DATETIME, the integer 0 being the zero datetime, and warns that the active file is not purged;
 * the emulator keeps the other files. The
 * BINLOG statement decodes its base64 text (ER_BASE64_DECODE_ERROR) and reads the first event:
 * fewer than 13 bytes, or an event longer than the text, is a syntax error, and only a format
 * description or row event is allowed (ER_ONLY_FD_AND_RBR_EVENTS_ALLOWED_IN_BINLOG_STATEMENT,
 * naming the type of the event). The emulator applies no event, so a format description or
 * row event, which the server would decode, is refused as one it cannot decode (verified on a
 * live 8.4 server). None of the statements commits the open transaction.
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $log = $session->instance->registry->binaryLog;
        if ($statement instanceof PurgeLogsTo) {
            $number = $log->find((new Literals())->bytes($statement->file)) ?? throw ErrorCode::UnknownTargetBinlog->error();
            $log->purge($number);
        } elseif ($statement instanceof PurgeLogsBefore) {
            $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
            $moment = $planner->compiler->compile($statement->moment, new Scope());
            $value = $moment->evaluate(new Frame($context));
            if ($value !== null && !(is_int($value) && $value === 0)) {
                (new Moments())->convert($value, $moment->domain(), new Domain(Kind::DateTime, Field::DateTime, 19, 0, false), $context);
            }
            $session->diagnostics->warning(ErrorCode::ActiveLogNotPurged, ErrorCode::ActiveLogNotPurged->message('./' . $log->active()));
        } elseif ($statement instanceof BinlogEvent) {
            $this->event((new Literals())->bytes($statement->events));
        }

        return new Completion(0, 0, $session->diagnostics->count());
    }

    /**
     * Decodes the events of a BINLOG statement and refuses the first.
     *
     * @throws \MySqlMemory\Error\SqlError Always: the text is not base64, the event is malformed, or it is not one the statement allows
     */
    public function event(string $text): void
    {
        $compact = (string) preg_replace('/\s+/', '', $text);
        $bytes = strlen($compact) % 4 === 0 ? base64_decode($compact, true) : false;
        if ($bytes === false) {
            throw ErrorCode::Base64DecodeFailed->error();
        }
        if (strlen($bytes) < 13) {
            throw ErrorCode::SyntaxError->error();
        }
        $length = unpack('V', substr($bytes, 9, 4));
        $type = ord($bytes[4]);
        if (!is_array($length) || $length[1] > strlen($bytes) || in_array($type, self::DECODED, true)) {
            throw ErrorCode::SyntaxError->error();
        }
        if ($type === 29) {
            throw ErrorCode::NoFormatDescriptionEvent->error('Rows_query');
        }

        throw ErrorCode::BinlogEventRefused->error(self::EVENTS[$type] ?? 'Unknown');
    }
}
