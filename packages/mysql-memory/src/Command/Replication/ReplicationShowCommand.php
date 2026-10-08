<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\BinaryLog;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes the SHOW statements of replication: SHOW REPLICA STATUS, SHOW REPLICAS, SHOW BINARY LOGS, SHOW BINARY LOG STATUS, SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS.
 *
 * The emulated server is neither a configured replica nor a source with replicas, so SHOW
 * REPLICA STATUS and SHOW REPLICAS list no row. The binary log files are those of the server's
 * binary log; the relay log of the default channel is one file, named after relay_log, holding
 * the two events every log starts with. SHOW BINLOG EVENTS reads the first file of the index
 * unless IN names another. FROM starts at the first event at or after the position; a position
 * beyond 17592186040320 cannot be read. LIMIT 0 lists every event. A LIMIT operand naming a
 * variable is ER_SP_UNDECLARED_VAR (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-replica-status.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-replicas.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-binary-logs.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-binary-log-status.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-binlog-events.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-relaylog-events.html.
 *
 * @visibility MySqlMemory
 */
final class ReplicationShowCommand implements Command
{
    /**
     * The columns of SHOW REPLICA STATUS: the name, and the length in characters of a text column or the type and display length of a number.
     */
    public const STATUS = [
        ['Replica_IO_State', 14], ['Source_Host', 256], ['Source_User', 97], ['Source_Port', Field::Long, 8], ['Connect_Retry', Field::Long, 11],
        ['Source_Log_File', 512], ['Read_Source_Log_Pos', Field::LongLong, 11], ['Relay_Log_File', 512], ['Relay_Log_Pos', Field::LongLong, 11],
        ['Relay_Source_Log_File', 512], ['Replica_IO_Running', 3], ['Replica_SQL_Running', 3], ['Replicate_Do_DB', 20], ['Replicate_Ignore_DB', 20],
        ['Replicate_Do_Table', 20], ['Replicate_Ignore_Table', 23], ['Replicate_Wild_Do_Table', 24], ['Replicate_Wild_Ignore_Table', 28],
        ['Last_Errno', Field::Long, 5], ['Last_Error', 20], ['Skip_Counter', Field::Long, 11], ['Exec_Source_Log_Pos', Field::LongLong, 11],
        ['Relay_Log_Space', Field::LongLong, 11], ['Until_Condition', 6], ['Until_Log_File', 512], ['Until_Log_Pos', Field::LongLong, 11],
        ['Source_SSL_Allowed', 7], ['Source_SSL_CA_File', 512], ['Source_SSL_CA_Path', 512], ['Source_SSL_Cert', 512], ['Source_SSL_Cipher', 512],
        ['Source_SSL_Key', 512], ['Seconds_Behind_Source', Field::LongLong, 11], ['Source_SSL_Verify_Server_Cert', 3], ['Last_IO_Errno', Field::Long, 5],
        ['Last_IO_Error', 20], ['Last_SQL_Errno', Field::Long, 5], ['Last_SQL_Error', 20], ['Replicate_Ignore_Server_Ids', 512],
        ['Source_Server_Id', Field::Long, 9], ['Source_UUID', 36], ['Source_Info_File', 1024], ['SQL_Delay', Field::Long, 11],
        ['SQL_Remaining_Delay', Field::Long, 9], ['Replica_SQL_Running_State', 20], ['Source_Retry_Count', Field::LongLong, 11], ['Source_Bind', 256],
        ['Last_IO_Error_Timestamp', 20], ['Last_SQL_Error_Timestamp', 20], ['Source_SSL_Crl', 512], ['Source_SSL_Crlpath', 512],
        ['Retrieved_Gtid_Set', 0], ['Executed_Gtid_Set', 0], ['Auto_Position', Field::Long, 9], ['Replicate_Rewrite_DB', 24], ['Channel_Name', 192],
        ['Source_TLS_Version', 512], ['Source_public_key_path', 512], ['Get_Source_public_key', Field::Long, 9], ['Network_Namespace', 193],
    ];

    /**
     * The columns of SHOW REPLICAS.
     */
    public const REPLICAS = [['Server_Id', Field::Long, 11], ['Host', 255], ['Port', Field::Long, 8], ['Source_Id', Field::Long, 11], ['Replica_UUID', 36]];

    /**
     * The columns of SHOW BINARY LOGS.
     */
    public const LOGS = [['Log_name', 255], ['File_size', Field::LongLong, 21], ['Encrypted', 3]];

    /**
     * The columns of SHOW BINARY LOG STATUS.
     */
    public const LOG_STATUS = [['File', 512], ['Position', Field::LongLong, 21], ['Binlog_Do_DB', 255], ['Binlog_Ignore_DB', 255], ['Executed_Gtid_Set', 0]];

    /**
     * The columns of SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS.
     */
    public const EVENTS = [['Log_name', 20], ['Pos', Field::LongLong, 12], ['Event_type', 20], ['Server_id', Field::Long, 11], ['End_log_pos', Field::LongLong, 12], ['Info', 20]];

    /**
     * The highest position SHOW BINLOG EVENTS reads from.
     */
    public const LAST_POSITION = '17592186040320';

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the rows of the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $log = $session->instance->registry->binaryLog;
        $version = $session->instance->version;
        (new ReplicaCommand())->deprecated($statement, $session, $context);
        (new LegacyReplication())->check($statement, $session);
        if ($statement instanceof ShowReplicaStatus) {
            (new ReplicaCommand())->channel($statement->channel);

            return $this->listing($statement->terminology === Terminology::Legacy ? $this->legacy(self::STATUS) : self::STATUS, [], $context);
        }
        if ($statement instanceof ShowBinaryLogs) {
            return $this->listing(self::LOGS, array_map(static fn (int $file): array => [BinaryLog::name($file), $log->size($file, $version), 'No'], $log->files), $context);
        }
        if ($statement instanceof ShowBinaryLogStatus) {
            $active = $log->files[count($log->files) - 1];

            return $this->listing(self::LOG_STATUS, [[BinaryLog::name($active), $log->size($active, $version), '', '', '']], $context);
        }
        if ($statement instanceof ShowBinlogEvents) {
            $this->limit($statement->limit);
            $file = $statement->file === null ? $log->files[0] : $log->find((new Literals())->bytes($statement->file));
            if ($file === null) {
                throw AdministrationError::CommandFailed->error('SHOW BINLOG EVENTS', 'Could not find target log');
            }
            $events = array_map(static fn (array $event): array => [BinaryLog::name($file), ...$event], $log->events($file, $version));

            return $this->listing(self::EVENTS, $this->window($events, $statement->position === null ? '4' : (new Literals())->number($statement->position), $statement->limit, 'SHOW BINLOG EVENTS'), $context);
        }
        if ($statement instanceof ShowRelaylogEvents) {
            $this->limit($statement->limit);
            (new ReplicaCommand())->channel($statement->channel);
            $name = $session->variables->read('relay_log') . '.000001';
            if ($statement->file !== null && (new Literals())->bytes($statement->file) !== $name) {
                throw AdministrationError::CommandFailed->error('SHOW RELAYLOG EVENTS', 'Could not find target log');
            }
            $events = array_map(static fn (array $event): array => [$name, ...$event], array_slice((new BinaryLog())->events(1, $version), 0, 2));

            return $this->listing(self::EVENTS, $this->window($events, $statement->position === null ? '4' : (new Literals())->number($statement->position), $statement->limit, 'SHOW RELAYLOG EVENTS'), $context);
        }
        if ($statement instanceof ShowReplicas) {
            return $this->listing($statement->terminology === Terminology::Legacy ? $this->legacy(self::REPLICAS) : self::REPLICAS, [], $context);
        }
        throw StatementError::NotSupportedYet->error('this replication statement');
    }

    /**
     * Answers columns named in the legacy vocabulary, as SHOW SLAVE STATUS and SHOW SLAVE HOSTS name them: MASTER for SOURCE and SLAVE for REPLICA.
     *
     * The legacy names keep their own letter case: Server_id, Master_id and Get_master_public_key
     * (verified on a live 8.0 server).
     *
     * @param list<array{string, int}|array{string, Field, int}> $columns
     * @return list<array{string, int}|array{string, Field, int}>
     */
    public function legacy(array $columns): array
    {
        $names = ['Server_Id' => 'Server_id', 'Source_Id' => 'Master_id', 'Get_Source_public_key' => 'Get_master_public_key'];

        return array_map(static function (array $column) use ($names): array {
            $column[0] = $names[$column[0]] ?? (string) preg_replace(['/(^|_)Source(?=_|$)/', '/(^|_)Replica(?=_|$)/'], ['$1Master', '$1Slave'], $column[0]);

            return $column;
        }, $columns);
    }

    /**
     * Answers the events from a position, within a LIMIT.
     *
     * @param list<list<int|string>> $events Each event, its log name first
     * @param numeric-string $position The position FROM names
     * @return list<list<int|string>>
     *
     * @throws \MySqlMemory\Error\SqlError When the position cannot be read
     */
    public function window(array $events, string $position, ?Limit $limit, string $command): array
    {
        if (bccomp($position, self::LAST_POSITION) > 0) {
            $shown = bccomp($position, '18446744073709551615') > 0 ? '18446744073709551615' : $position;

            throw AdministrationError::CommandFailed->error($command, 'Error reading Log_event at position ' . $shown . ': Failed decoding event: I/O error reading log event');
        }
        $from = max(4, (int) $position);
        $events = array_values(array_filter($events, static fn (array $event): bool => (int) $event[1] >= $from));
        if ($limit instanceof RowLimit) {
            $count = $this->bound($limit->count);
            $offset = $this->bound($limit->offset) ?? 0;
            $events = array_slice($events, $offset, $count === 0 ? null : $count);
        }

        return $events;
    }

    /**
     * Refuses a LIMIT operand that names a variable.
     *
     * @throws \MySqlMemory\Error\SqlError When an operand names a variable
     */
    public function limit(?Limit $limit): void
    {
        if (!$limit instanceof RowLimit) {
            return;
        }
        foreach ([$limit->offset, $limit->count] as $operand) {
            if ($operand instanceof ProgramVariable) {
                throw ProgramError::UndeclaredVariable->error($operand->name->value);
            }
        }
    }

    /**
     * Answers the value of a LIMIT operand, an unsigned integer literal; none for an absent one.
     */
    public function bound(?Scalar $operand): ?int
    {
        if ($operand === null) {
            return null;
        }
        if (!$operand instanceof NumberLiteral) {
            return 0;
        }

        $digits = ltrim($operand->text, '0');

        return strlen($digits) > 18 ? PHP_INT_MAX : (int) $digits;
    }

    /**
     * Answers the result set of rows under columns.
     *
     * @param list<array{string, int}|array{string, Field, int}> $columns Each column: a name and the characters of a text, or a name, a number type and its display length
     * @param list<list<int|string>> $rows
     */
    public function listing(array $columns, array $rows, Context $context): Reply
    {
        $number = ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value;
        $headings = array_map(static fn (array $column): Heading => count($column) === 2 ? Heading::text($column[0], Field::VarString, $column[1], ColumnFlag::NotNull->value, 31) : new Heading($column[0], $column[1], $column[2], $number), $columns);

        return (new Listing($headings))->sent($rows, $context);
    }
}
