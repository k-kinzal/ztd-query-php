<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

/**
 * The checks the server's parser applies to the values of replication statements.
 *
 * Each case names the server error the statement fails with and describes
 * it. The server raises them while it parses, after the grammar has accepted
 * the statement.
 * Source: sql/sql_yacc.yy of MySQL 5.6.51 to 9.1.0 (the actions of
 * TEXT_STRING_sys_nonewline, source_def, replica_until, start_replica_stmt,
 * source_reset_options, filter_wild_db_table_string, dec_num_error and
 * group_replication_password), https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Describing a check
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::PasswordTooLong->value // => 'A replication password is longer than 32 characters (ER_CHANGE_SOURCE_PASSWORD_LENGTH).'
 */
enum ReplicationError: string
{
    case LineFeed = 'The value may not contain a line feed (ER_WRONG_VALUE).';
    case PasswordTooLong = 'A replication password is longer than 32 characters (ER_CHANGE_SOURCE_PASSWORD_LENGTH).';
    case GroupPasswordTooLong = 'A group replication password is longer than 32 characters (ER_GROUP_REPLICATION_PASSWORD_LENGTH).';
    case DelayOutOfRange = 'The replication delay is greater than 2147483647 seconds (ER_SOURCE_DELAY_VALUE_OUT_OF_RANGE).';
    case HeartbeatOutOfRange = 'The heartbeat period is greater than 4294967 seconds (ER_REPLICA_HEARTBEAT_VALUE_OUT_OF_RANGE).';
    case RowFormatValue = 'REQUIRE_ROW_FORMAT accepts only 0 or 1 (ER_REQUIRE_ROW_FORMAT_INVALID_VALUE).';
    case SwitchValue = 'SOURCE_CONNECTION_AUTO_FAILOVER and GTID_ONLY accept only 0 or 1 (ER_PARSE_ERROR).';
    case FractionalNumber = 'Only integers are allowed as the value (ER_ONLY_INTEGERS_ALLOWED).';
    case InvalidUuid = 'The value is not a UUID (ER_WRONG_VALUE).';
    case UntilCondition = 'The UNTIL condition names neither a complete position, nor a GTID set, nor only SQL_AFTER_MTS_GAPS (ER_BAD_REPLICA_UNTIL_COND).';
    case ApplierWithCredentials = 'Connection options need the receiver thread; SQL_THREAD alone is started (ER_SQLTHREAD_WITH_SECURE_REPLICA).';
    case FileNumberOutOfRange = 'The binary log file number is 0 or greater than 2000000000 (ER_RESET_SOURCE_TO_VALUE_OUT_OF_RANGE).';
    case WildPattern = 'A wildcard table filter pattern has no dot between database and table (ER_INVALID_RPL_WILD_TABLE_FILTER_PATTERN).';
}
