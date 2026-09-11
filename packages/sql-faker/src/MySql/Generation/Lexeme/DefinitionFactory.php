<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexicalDefinition;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\DollarQuotedDomain;
use SqlFaker\Grammar\Generation\Value\IdentifierDomain;
use SqlFaker\Grammar\Generation\Value\QuotedDomain;
use SqlFaker\Grammar\Generation\Value\RadixDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\WordDomain;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule;
use SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule;
use SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule;
use SqlFaker\MySql\Generation\Spacing\VariableSpacingRule;

/**
 * Declares scanner values, fixed spellings, contextual domains and spacing for each exact release.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/create_field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/table.cc#L3749-L3786
 */
final class DefinitionFactory
{
    /**
     * Composes the selected release directly from the declarations in this file.
     * @throws RuntimeException When the exact release is unsupported
     */
    public function create(string $version): LexicalDefinition
    {
        SqlVersion::resolve('mysql', $version);
        $keywords = [
            'symbols' => [
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                'ACCESSIBLE_SYM' => ['ACCESSIBLE'],
                'ACTION' => ['ACTION'],
                'ADD' => ['ADD'],
                'AFTER_SYM' => ['AFTER'],
                'AGAINST' => ['AGAINST'],
                'AGGREGATE_SYM' => ['AGGREGATE'],
                'ALGORITHM_SYM' => ['ALGORITHM'],
                'ALL' => ['ALL'],
                'ALTER' => ['ALTER'],
                'ANALYZE_SYM' => ['ANALYZE'],
                'AND_AND_SYM' => ['&&'],
                'AND_SYM' => ['AND'],
                'ANY_SYM' => ['ANY', 'SOME'],
                'AS' => ['AS'],
                'ASC' => ['ASC'],
                'ASCII_SYM' => ['ASCII'],
                'ASENSITIVE_SYM' => ['ASENSITIVE'],
                'AT_SYM' => ['AT'],
                'AUTOEXTEND_SIZE_SYM' => ['AUTOEXTEND_SIZE'],
                'AUTO_INC' => ['AUTO_INCREMENT'],
                'AVG_ROW_LENGTH' => ['AVG_ROW_LENGTH'],
                'AVG_SYM' => ['AVG'],
                'BACKUP_SYM' => ['BACKUP'],
                'BEFORE_SYM' => ['BEFORE'],
                'BEGIN_SYM' => ['BEGIN'],
                'BETWEEN_SYM' => ['BETWEEN'],
                'BINLOG_SYM' => ['BINLOG'],
                'BIT_SYM' => ['BIT'],
                'BLOB_SYM' => ['BLOB'],
                'BLOCK_SYM' => ['BLOCK'],
                'BOOLEAN_SYM' => ['BOOLEAN'],
                'BOOL_SYM' => ['BOOL'],
                'BOTH' => ['BOTH'],
                'BTREE_SYM' => ['BTREE'],
                'BY' => ['BY'],
                'BYTE_SYM' => ['BYTE'],
                'CACHE_SYM' => ['CACHE'],
                'CALL_SYM' => ['CALL'],
                'CASCADE' => ['CASCADE'],
                'CASCADED' => ['CASCADED'],
                'CASE_SYM' => ['CASE'],
                'CATALOG_NAME_SYM' => ['CATALOG_NAME'],
                'CHAIN_SYM' => ['CHAIN'],
                'CHANGE' => ['CHANGE'],
                'CHANGED' => ['CHANGED'],
                'CHARSET' => ['CHARSET'],
                'CHAR_SYM' => ['CHAR', 'CHARACTER'],
                'CHECKSUM_SYM' => ['CHECKSUM'],
                'CHECK_SYM' => ['CHECK'],
                'CIPHER_SYM' => ['CIPHER'],
                'CLASS_ORIGIN_SYM' => ['CLASS_ORIGIN'],
                'CLIENT_SYM' => ['CLIENT'],
                'CLOSE_SYM' => ['CLOSE'],
                'COALESCE' => ['COALESCE'],
                'CODE_SYM' => ['CODE'],
                'COLLATE_SYM' => ['COLLATE'],
                'COLLATION_SYM' => ['COLLATION'],
                'COLUMNS' => ['COLUMNS', 'FIELDS'],
                'COLUMN_FORMAT_SYM' => ['COLUMN_FORMAT'],
                'COLUMN_NAME_SYM' => ['COLUMN_NAME'],
                'COLUMN_SYM' => ['COLUMN'],
                'COMMENT_SYM' => ['COMMENT'],
                'COMMITTED_SYM' => ['COMMITTED'],
                'COMMIT_SYM' => ['COMMIT'],
                'COMPACT_SYM' => ['COMPACT'],
                'COMPLETION_SYM' => ['COMPLETION'],
                'COMPRESSED_SYM' => ['COMPRESSED'],
                'CONCURRENT' => ['CONCURRENT'],
                'CONDITION_SYM' => ['CONDITION'],
                'CONNECTION_SYM' => ['CONNECTION'],
                'CONSISTENT_SYM' => ['CONSISTENT'],
                'CONSTRAINT' => ['CONSTRAINT'],
                'CONSTRAINT_CATALOG_SYM' => ['CONSTRAINT_CATALOG'],
                'CONSTRAINT_NAME_SYM' => ['CONSTRAINT_NAME'],
                'CONSTRAINT_SCHEMA_SYM' => ['CONSTRAINT_SCHEMA'],
                'CONTAINS_SYM' => ['CONTAINS'],
                'CONTEXT_SYM' => ['CONTEXT'],
                'CONTINUE_SYM' => ['CONTINUE'],
                'CONVERT_SYM' => ['CONVERT'],
                'CPU_SYM' => ['CPU'],
                'CREATE' => ['CREATE'],
                'CROSS' => ['CROSS'],
                'CUBE_SYM' => ['CUBE'],
                'CURDATE' => ['CURRENT_DATE'],
                'CURRENT_SYM' => ['CURRENT'],
                'CURRENT_USER' => ['CURRENT_USER'],
                'CURSOR_NAME_SYM' => ['CURSOR_NAME'],
                'CURSOR_SYM' => ['CURSOR'],
                'CURTIME' => ['CURRENT_TIME'],
                'DATABASE' => ['DATABASE', 'SCHEMA'],
                'DATABASES' => ['DATABASES', 'SCHEMAS'],
                'DATAFILE_SYM' => ['DATAFILE'],
                'DATA_SYM' => ['DATA'],
                'DATE_SYM' => ['DATE'],
                'DAY_HOUR_SYM' => ['DAY_HOUR'],
                'DAY_MICROSECOND_SYM' => ['DAY_MICROSECOND'],
                'DAY_MINUTE_SYM' => ['DAY_MINUTE'],
                'DAY_SECOND_SYM' => ['DAY_SECOND'],
                'DAY_SYM' => ['DAY', 'SQL_TSI_DAY'],
                'DEALLOCATE_SYM' => ['DEALLOCATE'],
                'DECIMAL_SYM' => ['DEC', 'DECIMAL'],
                'DECLARE_SYM' => ['DECLARE'],
                'DEFAULT_AUTH_SYM' => ['DEFAULT_AUTH'],
                'DEFINER_SYM' => ['DEFINER'],
                'DELAYED_SYM' => ['DELAYED'],
                'DELAY_KEY_WRITE_SYM' => ['DELAY_KEY_WRITE'],
                'DELETE_SYM' => ['DELETE'],
                'DESC' => ['DESC'],
                'DESCRIBE' => ['DESCRIBE', 'EXPLAIN'],
                'DETERMINISTIC_SYM' => ['DETERMINISTIC'],
                'DIAGNOSTICS_SYM' => ['DIAGNOSTICS'],
                'DIRECTORY_SYM' => ['DIRECTORY'],
                'DISABLE_SYM' => ['DISABLE'],
                'DISK_SYM' => ['DISK'],
                'DISTINCT' => ['DISTINCT', 'DISTINCTROW'],
                'DIV_SYM' => ['DIV'],
                'DOUBLE_SYM' => ['DOUBLE', 'FLOAT8'],
                'DO_SYM' => ['DO'],
                'DROP' => ['DROP'],
                'DUAL_SYM' => ['DUAL'],
                'DUMPFILE' => ['DUMPFILE'],
                'DUPLICATE_SYM' => ['DUPLICATE'],
                'DYNAMIC_SYM' => ['DYNAMIC'],
                'EACH_SYM' => ['EACH'],
                'ELSE' => ['ELSE'],
                'ELSEIF_SYM' => ['ELSEIF'],
                'ENABLE_SYM' => ['ENABLE'],
                'ENCLOSED' => ['ENCLOSED'],
                'END' => ['END'],
                'ENDS_SYM' => ['ENDS'],
                'ENGINES_SYM' => ['ENGINES'],
                'ENGINE_SYM' => ['ENGINE'],
                'EQ' => ['='],
                'EQUAL_SYM' => ['<=>'],
                'ERRORS' => ['ERRORS'],
                'ERROR_SYM' => ['ERROR'],
                'ESCAPED' => ['ESCAPED'],
                'ESCAPE_SYM' => ['ESCAPE'],
                'EVENTS_SYM' => ['EVENTS'],
                'EVENT_SYM' => ['EVENT'],
                'EVERY_SYM' => ['EVERY'],
                'EXCHANGE_SYM' => ['EXCHANGE'],
                'EXECUTE_SYM' => ['EXECUTE'],
                'EXISTS' => ['EXISTS'],
                'EXIT_SYM' => ['EXIT'],
                'EXPANSION_SYM' => ['EXPANSION'],
                'EXPIRE_SYM' => ['EXPIRE'],
                'EXPORT_SYM' => ['EXPORT'],
                'EXTENDED_SYM' => ['EXTENDED'],
                'EXTENT_SIZE_SYM' => ['EXTENT_SIZE'],
                'FALSE_SYM' => ['FALSE'],
                'FAST_SYM' => ['FAST'],
                'FAULTS_SYM' => ['FAULTS'],
                'FETCH_SYM' => ['FETCH'],
                'FILE_SYM' => ['FILE'],
                'FIRST_SYM' => ['FIRST'],
                'FIXED_SYM' => ['FIXED'],
                'FLOAT_SYM' => ['FLOAT', 'FLOAT4'],
                'FLUSH_SYM' => ['FLUSH'],
                'FORCE_SYM' => ['FORCE'],
                'FOREIGN' => ['FOREIGN'],
                'FORMAT_SYM' => ['FORMAT'],
                'FOR_SYM' => ['FOR'],
                'FOUND_SYM' => ['FOUND'],
                'FROM' => ['FROM'],
                'FULL' => ['FULL'],
                'FULLTEXT_SYM' => ['FULLTEXT'],
                'FUNCTION_SYM' => ['FUNCTION'],
                'GE' => ['>='],
                'GENERAL' => ['GENERAL'],
                'GEOMETRY_SYM' => ['GEOMETRY'],
                'GET_FORMAT' => ['GET_FORMAT'],
                'GET_SYM' => ['GET'],
                'GLOBAL_SYM' => ['GLOBAL'],
                'GRANT' => ['GRANT'],
                'GRANTS' => ['GRANTS'],
                'GROUP_SYM' => ['GROUP'],
                'GT_SYM' => ['>'],
                'HANDLER_SYM' => ['HANDLER'],
                'HASH_SYM' => ['HASH'],
                'HAVING' => ['HAVING'],
                'HELP_SYM' => ['HELP'],
                'HIGH_PRIORITY' => ['HIGH_PRIORITY'],
                'HOSTS_SYM' => ['HOSTS'],
                'HOST_SYM' => ['HOST'],
                'HOUR_MICROSECOND_SYM' => ['HOUR_MICROSECOND'],
                'HOUR_MINUTE_SYM' => ['HOUR_MINUTE'],
                'HOUR_SECOND_SYM' => ['HOUR_SECOND'],
                'HOUR_SYM' => ['HOUR', 'SQL_TSI_HOUR'],
                'IDENTIFIED_SYM' => ['IDENTIFIED'],
                'IF' => ['IF'],
                'IGNORE_SERVER_IDS_SYM' => ['IGNORE_SERVER_IDS'],
                'IGNORE_SYM' => ['IGNORE'],
                'IMPORT' => ['IMPORT'],
                'INDEXES' => ['INDEXES'],
                'INDEX_SYM' => ['INDEX'],
                'INITIAL_SIZE_SYM' => ['INITIAL_SIZE'],
                'INNER_SYM' => ['INNER'],
                'INOUT_SYM' => ['INOUT'],
                'INSENSITIVE_SYM' => ['INSENSITIVE'],
                'INSERT_METHOD' => ['INSERT_METHOD'],
                'INSTALL_SYM' => ['INSTALL'],
                'INTERVAL_SYM' => ['INTERVAL'],
                'INTO' => ['INTO'],
                'INT_SYM' => ['INT', 'INT4', 'INTEGER'],
                'INVOKER_SYM' => ['INVOKER'],
                'IN_SYM' => ['IN'],
                'IO_AFTER_GTIDS' => ['IO_AFTER_GTIDS'],
                'IO_BEFORE_GTIDS' => ['IO_BEFORE_GTIDS'],
                'IO_SYM' => ['IO'],
                'IPC_SYM' => ['IPC'],
                'IS' => ['IS'],
                'ISOLATION' => ['ISOLATION'],
                'ISSUER_SYM' => ['ISSUER'],
                'ITERATE_SYM' => ['ITERATE'],
                'JOIN_SYM' => ['JOIN'],
                'KEYS' => ['KEYS'],
                'KEY_BLOCK_SIZE' => ['KEY_BLOCK_SIZE'],
                'KEY_SYM' => ['KEY'],
                'KILL_SYM' => ['KILL'],
                'LANGUAGE_SYM' => ['LANGUAGE'],
                'LAST_SYM' => ['LAST'],
                'LE' => ['<='],
                'LEADING' => ['LEADING'],
                'LEAVES' => ['LEAVES'],
                'LEAVE_SYM' => ['LEAVE'],
                'LEFT' => ['LEFT'],
                'LESS_SYM' => ['LESS'],
                'LEVEL_SYM' => ['LEVEL'],
                'LIKE' => ['LIKE'],
                'LIMIT' => ['LIMIT'],
                'LINEAR_SYM' => ['LINEAR'],
                'LINES' => ['LINES'],
                'LIST_SYM' => ['LIST'],
                'LOAD' => ['LOAD'],
                'LOCAL_SYM' => ['LOCAL'],
                'LOCKS_SYM' => ['LOCKS'],
                'LOCK_SYM' => ['LOCK'],
                'LOGFILE_SYM' => ['LOGFILE'],
                'LOGS_SYM' => ['LOGS'],
                'LONG_SYM' => ['LONG'],
                'LOOP_SYM' => ['LOOP'],
                'LOW_PRIORITY' => ['LOW_PRIORITY'],
                'LT' => ['<'],
                'MASTER_SYM' => ['MASTER'],
                'MATCH' => ['MATCH'],
                'MAX_CONNECTIONS_PER_HOUR' => ['MAX_CONNECTIONS_PER_HOUR'],
                'MAX_QUERIES_PER_HOUR' => ['MAX_QUERIES_PER_HOUR'],
                'MAX_ROWS' => ['MAX_ROWS'],
                'MAX_SIZE_SYM' => ['MAX_SIZE'],
                'MAX_UPDATES_PER_HOUR' => ['MAX_UPDATES_PER_HOUR'],
                'MAX_USER_CONNECTIONS_SYM' => ['MAX_USER_CONNECTIONS'],
                'MAX_VALUE_SYM' => ['MAXVALUE'],
                'MEDIUM_SYM' => ['MEDIUM'],
                'MEMORY_SYM' => ['MEMORY'],
                'MERGE_SYM' => ['MERGE'],
                'MESSAGE_TEXT_SYM' => ['MESSAGE_TEXT'],
                'MICROSECOND_SYM' => ['MICROSECOND'],
                'MIGRATE_SYM' => ['MIGRATE'],
                'MINUTE_MICROSECOND_SYM' => ['MINUTE_MICROSECOND'],
                'MINUTE_SECOND_SYM' => ['MINUTE_SECOND'],
                'MINUTE_SYM' => ['MINUTE', 'SQL_TSI_MINUTE'],
                'MIN_ROWS' => ['MIN_ROWS'],
                'MODE_SYM' => ['MODE'],
                'MODIFIES_SYM' => ['MODIFIES'],
                'MODIFY_SYM' => ['MODIFY'],
                'MOD_SYM' => ['MOD'],
                'MONTH_SYM' => ['MONTH', 'SQL_TSI_MONTH'],
                'MUTEX_SYM' => ['MUTEX'],
                'MYSQL_ERRNO_SYM' => ['MYSQL_ERRNO'],
                'NAMES_SYM' => ['NAMES'],
                'NAME_SYM' => ['NAME'],
                'NATIONAL_SYM' => ['NATIONAL'],
                'NATURAL' => ['NATURAL'],
                'NCHAR_SYM' => ['NCHAR'],
                'NDBCLUSTER_SYM' => ['NDB', 'NDBCLUSTER'],
                'NE' => ['<>', '!='],
                'NEW_SYM' => ['NEW'],
                'NEXT_SYM' => ['NEXT'],
                'NODEGROUP_SYM' => ['NODEGROUP'],
                'NONE_SYM' => ['NONE'],
                'NOT_SYM' => ['NOT'],
                'NOW_SYM' => ['CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP'],
                'NO_SYM' => ['NO'],
                'NO_WAIT_SYM' => ['NO_WAIT'],
                'NO_WRITE_TO_BINLOG' => ['NO_WRITE_TO_BINLOG'],
                'NULL_SYM' => ['NULL'],
                'NUMBER_SYM' => ['NUMBER'],
                'NUMERIC_SYM' => ['NUMERIC'],
                'NVARCHAR_SYM' => ['NVARCHAR'],
                'OFFSET_SYM' => ['OFFSET'],
                'ONE_SYM' => ['ONE'],
                'ONLY_SYM' => ['ONLY'],
                'OPEN_SYM' => ['OPEN'],
                'OPTIMIZE' => ['OPTIMIZE'],
                'OPTION' => ['OPTION'],
                'OPTIONALLY' => ['OPTIONALLY'],
                'OPTIONS_SYM' => ['OPTIONS'],
                'ORDER_SYM' => ['ORDER'],
                'OR_OR_SYM' => ['||'],
                'OR_SYM' => ['OR'],
                'OUTFILE' => ['OUTFILE'],
                'OUT_SYM' => ['OUT'],
                'OWNER_SYM' => ['OWNER'],
                'PACK_KEYS_SYM' => ['PACK_KEYS'],
                'PAGE_SYM' => ['PAGE'],
                'PARSER_SYM' => ['PARSER'],
                'PARTIAL' => ['PARTIAL'],
                'PARTITIONING_SYM' => ['PARTITIONING'],
                'PARTITIONS_SYM' => ['PARTITIONS'],
                'PARTITION_SYM' => ['PARTITION'],
                'PASSWORD' => ['PASSWORD'],
                'PHASE_SYM' => ['PHASE'],
                'PLUGINS_SYM' => ['PLUGINS'],
                'PLUGIN_DIR_SYM' => ['PLUGIN_DIR'],
                'PLUGIN_SYM' => ['PLUGIN'],
                'POINT_SYM' => ['POINT'],
                'PORT_SYM' => ['PORT'],
                'PRECISION' => ['PRECISION'],
                'PREPARE_SYM' => ['PREPARE'],
                'PRESERVE_SYM' => ['PRESERVE'],
                'PREV_SYM' => ['PREV'],
                'PRIMARY_SYM' => ['PRIMARY'],
                'PRIVILEGES' => ['PRIVILEGES'],
                'PROCEDURE_SYM' => ['PROCEDURE'],
                'PROCESS' => ['PROCESS'],
                'PROCESSLIST_SYM' => ['PROCESSLIST'],
                'PROFILES_SYM' => ['PROFILES'],
                'PROFILE_SYM' => ['PROFILE'],
                'PROXY_SYM' => ['PROXY'],
                'PURGE' => ['PURGE'],
                'QUARTER_SYM' => ['QUARTER', 'SQL_TSI_QUARTER'],
                'QUERY_SYM' => ['QUERY'],
                'QUICK' => ['QUICK'],
                'RANGE_SYM' => ['RANGE'],
                'READS_SYM' => ['READS'],
                'READ_ONLY_SYM' => ['READ_ONLY'],
                'READ_SYM' => ['READ'],
                'READ_WRITE_SYM' => ['READ_WRITE'],
                'REBUILD_SYM' => ['REBUILD'],
                'RECOVER_SYM' => ['RECOVER'],
                'REDO_BUFFER_SIZE_SYM' => ['REDO_BUFFER_SIZE'],
                'REDUNDANT_SYM' => ['REDUNDANT'],
                'REFERENCES' => ['REFERENCES'],
                'REGEXP' => ['REGEXP', 'RLIKE'],
                'RELAY' => ['RELAY'],
                'RELAYLOG_SYM' => ['RELAYLOG'],
                'RELAY_LOG_FILE_SYM' => ['RELAY_LOG_FILE'],
                'RELAY_LOG_POS_SYM' => ['RELAY_LOG_POS'],
                'RELAY_THREAD' => ['IO_THREAD', 'RELAY_THREAD'],
                'RELEASE_SYM' => ['RELEASE'],
                'RELOAD' => ['RELOAD'],
                'REMOVE_SYM' => ['REMOVE'],
                'RENAME' => ['RENAME'],
                'REORGANIZE_SYM' => ['REORGANIZE'],
                'REPAIR' => ['REPAIR'],
                'REPEATABLE_SYM' => ['REPEATABLE'],
                'REPEAT_SYM' => ['REPEAT'],
                'REPLICATION' => ['REPLICATION'],
                'REQUIRE_SYM' => ['REQUIRE'],
                'RESET_SYM' => ['RESET'],
                'RESIGNAL_SYM' => ['RESIGNAL'],
                'RESOURCES' => ['USER_RESOURCES'],
                'RESTORE_SYM' => ['RESTORE'],
                'RESTRICT' => ['RESTRICT'],
                'RESUME_SYM' => ['RESUME'],
                'RETURNED_SQLSTATE_SYM' => ['RETURNED_SQLSTATE'],
                'RETURNS_SYM' => ['RETURNS'],
                'RETURN_SYM' => ['RETURN'],
                'REVERSE_SYM' => ['REVERSE'],
                'REVOKE' => ['REVOKE'],
                'RIGHT' => ['RIGHT'],
                'ROLLBACK_SYM' => ['ROLLBACK'],
                'ROLLUP_SYM' => ['ROLLUP'],
                'ROUTINE_SYM' => ['ROUTINE'],
                'ROWS_SYM' => ['ROWS'],
                'ROW_COUNT_SYM' => ['ROW_COUNT'],
                'ROW_FORMAT_SYM' => ['ROW_FORMAT'],
                'ROW_SYM' => ['ROW'],
                'RTREE_SYM' => ['RTREE'],
                'SAVEPOINT_SYM' => ['SAVEPOINT'],
                'SCHEDULE_SYM' => ['SCHEDULE'],
                'SCHEMA_NAME_SYM' => ['SCHEMA_NAME'],
                'SECOND_MICROSECOND_SYM' => ['SECOND_MICROSECOND'],
                'SECOND_SYM' => ['SECOND', 'SQL_TSI_SECOND'],
                'SECURITY_SYM' => ['SECURITY'],
                'SELECT_SYM' => ['SELECT'],
                'SENSITIVE_SYM' => ['SENSITIVE'],
                'SEPARATOR_SYM' => ['SEPARATOR'],
                'SERIALIZABLE_SYM' => ['SERIALIZABLE'],
                'SERIAL_SYM' => ['SERIAL'],
                'SERVER_SYM' => ['SERVER'],
                'SESSION_SYM' => ['SESSION'],
                'SHARE_SYM' => ['SHARE'],
                'SHIFT_LEFT' => ['<<'],
                'SHIFT_RIGHT' => ['>>'],
                'SHOW' => ['SHOW'],
                'SHUTDOWN' => ['SHUTDOWN'],
                'SIGNAL_SYM' => ['SIGNAL'],
                'SIGNED_SYM' => ['SIGNED'],
                'SIMPLE_SYM' => ['SIMPLE'],
                'SLAVE' => ['SLAVE'],
                'SLOW' => ['SLOW'],
                'SNAPSHOT_SYM' => ['SNAPSHOT'],
                'SOCKET_SYM' => ['SOCKET'],
                'SONAME_SYM' => ['SONAME'],
                'SOUNDS_SYM' => ['SOUNDS'],
                'SOURCE_SYM' => ['SOURCE'],
                'SPATIAL_SYM' => ['SPATIAL'],
                'SPECIFIC_SYM' => ['SPECIFIC'],
                'SQLEXCEPTION_SYM' => ['SQLEXCEPTION'],
                'SQLSTATE_SYM' => ['SQLSTATE'],
                'SQLWARNING_SYM' => ['SQLWARNING'],
                'SQL_AFTER_GTIDS' => ['SQL_AFTER_GTIDS'],
                'SQL_AFTER_MTS_GAPS' => ['SQL_AFTER_MTS_GAPS'],
                'SQL_BEFORE_GTIDS' => ['SQL_BEFORE_GTIDS'],
                'SQL_BIG_RESULT' => ['SQL_BIG_RESULT'],
                'SQL_BUFFER_RESULT' => ['SQL_BUFFER_RESULT'],
                'SQL_CALC_FOUND_ROWS' => ['SQL_CALC_FOUND_ROWS'],
                'SQL_NO_CACHE_SYM' => ['SQL_NO_CACHE'],
                'SQL_SMALL_RESULT' => ['SQL_SMALL_RESULT'],
                'SQL_SYM' => ['SQL'],
                'SQL_THREAD' => ['SQL_THREAD'],
                'SSL_SYM' => ['SSL'],
                'STARTING' => ['STARTING'],
                'STARTS_SYM' => ['STARTS'],
                'START_SYM' => ['START'],
                'STATS_AUTO_RECALC_SYM' => ['STATS_AUTO_RECALC'],
                'STATS_PERSISTENT_SYM' => ['STATS_PERSISTENT'],
                'STATS_SAMPLE_PAGES_SYM' => ['STATS_SAMPLE_PAGES'],
                'STATUS_SYM' => ['STATUS'],
                'STOP_SYM' => ['STOP'],
                'STORAGE_SYM' => ['STORAGE'],
                'STRAIGHT_JOIN' => ['STRAIGHT_JOIN'],
                'STRING_SYM' => ['STRING'],
                'SUBCLASS_ORIGIN_SYM' => ['SUBCLASS_ORIGIN'],
                'SUBJECT_SYM' => ['SUBJECT'],
                'SUBPARTITIONS_SYM' => ['SUBPARTITIONS'],
                'SUBPARTITION_SYM' => ['SUBPARTITION'],
                'SUPER_SYM' => ['SUPER'],
                'SUSPEND_SYM' => ['SUSPEND'],
                'SWAPS_SYM' => ['SWAPS'],
                'SWITCHES_SYM' => ['SWITCHES'],
                'TABLES' => ['TABLES'],
                'TABLE_CHECKSUM_SYM' => ['TABLE_CHECKSUM'],
                'TABLE_NAME_SYM' => ['TABLE_NAME'],
                'TABLE_SYM' => ['TABLE'],
                'TEMPORARY' => ['TEMPORARY'],
                'TEMPTABLE_SYM' => ['TEMPTABLE'],
                'TERMINATED' => ['TERMINATED'],
                'TEXT_SYM' => ['TEXT'],
                'THAN_SYM' => ['THAN'],
                'THEN_SYM' => ['THEN'],
                'TIMESTAMP_ADD' => ['TIMESTAMPADD'],
                'TIMESTAMP_DIFF' => ['TIMESTAMPDIFF'],
                'TIME_SYM' => ['TIME'],
                'TO_SYM' => ['TO'],
                'TRAILING' => ['TRAILING'],
                'TRANSACTION_SYM' => ['TRANSACTION'],
                'TRIGGERS_SYM' => ['TRIGGERS'],
                'TRIGGER_SYM' => ['TRIGGER'],
                'TRUE_SYM' => ['TRUE'],
                'TRUNCATE_SYM' => ['TRUNCATE'],
                'TYPES_SYM' => ['TYPES'],
                'TYPE_SYM' => ['TYPE'],
                'UNCOMMITTED_SYM' => ['UNCOMMITTED'],
                'UNDEFINED_SYM' => ['UNDEFINED'],
                'UNDOFILE_SYM' => ['UNDOFILE'],
                'UNDO_BUFFER_SIZE_SYM' => ['UNDO_BUFFER_SIZE'],
                'UNDO_SYM' => ['UNDO'],
                'UNICODE_SYM' => ['UNICODE'],
                'UNINSTALL_SYM' => ['UNINSTALL'],
                'UNION_SYM' => ['UNION'],
                'UNIQUE_SYM' => ['UNIQUE'],
                'UNKNOWN_SYM' => ['UNKNOWN'],
                'UNLOCK_SYM' => ['UNLOCK'],
                'UNTIL_SYM' => ['UNTIL'],
                'UPDATE_SYM' => ['UPDATE'],
                'UPGRADE_SYM' => ['UPGRADE'],
                'USAGE' => ['USAGE'],
                'USER' => ['USER'],
                'USE_FRM' => ['USE_FRM'],
                'USE_SYM' => ['USE'],
                'USING' => ['USING'],
                'UTC_DATE_SYM' => ['UTC_DATE'],
                'UTC_TIMESTAMP_SYM' => ['UTC_TIMESTAMP'],
                'UTC_TIME_SYM' => ['UTC_TIME'],
                'VALUES' => ['VALUES'],
                'VALUE_SYM' => ['VALUE'],
                'VARIABLES' => ['VARIABLES'],
                'VARYING' => ['VARYING'],
                'VIEW_SYM' => ['VIEW'],
                'WAIT_SYM' => ['WAIT'],
                'WARNINGS' => ['WARNINGS'],
                'WEEK_SYM' => ['SQL_TSI_WEEK', 'WEEK'],
                'WEIGHT_STRING_SYM' => ['WEIGHT_STRING'],
                'WHEN_SYM' => ['WHEN'],
                'WHERE' => ['WHERE'],
                'WHILE_SYM' => ['WHILE'],
                'WITH' => ['WITH'],
                'WORK_SYM' => ['WORK'],
                'WRAPPER_SYM' => ['WRAPPER'],
                'WRITE_SYM' => ['WRITE'],
                'X509_SYM' => ['X509'],
                'XA_SYM' => ['XA'],
                'XML_SYM' => ['XML'],
                'XOR' => ['XOR'],
                'YEAR_MONTH_SYM' => ['YEAR_MONTH'],
                'YEAR_SYM' => ['SQL_TSI_YEAR', 'YEAR'],
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'ACCOUNT_SYM' => ['ACCOUNT'],
                        'ALWAYS_SYM' => ['ALWAYS'],
                        'CHANNEL_SYM' => ['CHANNEL'],
                        'COMPRESSION_SYM' => ['COMPRESSION'],
                        'ENCRYPTION_SYM' => ['ENCRYPTION'],
                        'FILE_BLOCK_SIZE_SYM' => ['FILE_BLOCK_SIZE'],
                        'FILTER_SYM' => ['FILTER'],
                        'FOLLOWS_SYM' => ['FOLLOWS'],
                        'GENERATED' => ['GENERATED'],
                        'GROUP_REPLICATION' => ['GROUP_REPLICATION'],
                        'INSTANCE_SYM' => ['INSTANCE'],
                        'JSON_SYM' => ['JSON'],
                        'NEVER_SYM' => ['NEVER'],
                        'OPTIMIZER_COSTS_SYM' => ['OPTIMIZER_COSTS'],
                        'PRECEDES_SYM' => ['PRECEDES'],
                        'REPLICATE_DO_DB' => ['REPLICATE_DO_DB'],
                        'REPLICATE_DO_TABLE' => ['REPLICATE_DO_TABLE'],
                        'REPLICATE_IGNORE_DB' => ['REPLICATE_IGNORE_DB'],
                        'REPLICATE_IGNORE_TABLE' => ['REPLICATE_IGNORE_TABLE'],
                        'REPLICATE_REWRITE_DB' => ['REPLICATE_REWRITE_DB'],
                        'REPLICATE_WILD_DO_TABLE' => ['REPLICATE_WILD_DO_TABLE'],
                        'REPLICATE_WILD_IGNORE_TABLE' => ['REPLICATE_WILD_IGNORE_TABLE'],
                        'ROTATE_SYM' => ['ROTATE'],
                        'STACKED_SYM' => ['STACKED'],
                        'STORED_SYM' => ['STORED'],
                        'TABLESPACE_SYM' => ['TABLESPACE'],
                        'VALIDATION_SYM' => ['VALIDATION'],
                        'VIRTUAL_SYM' => ['VIRTUAL'],
                        'WITHOUT_SYM' => ['WITHOUT'],
                        'XID_SYM' => ['XID'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'ACTIVE_SYM' => ['ACTIVE'],
                        'ADMIN_SYM' => ['ADMIN'],
                        'ARRAY_SYM' => ['ARRAY'],
                        'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM' => ['ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS'],
                        'ATTRIBUTE_SYM' => ['ATTRIBUTE'],
                        'AUTHENTICATION_SYM' => ['AUTHENTICATION'],
                        'BIGINT_SYM' => ['BIGINT', 'INT8'],
                        'BINARY_SYM' => ['BINARY'],
                        'BUCKETS_SYM' => ['BUCKETS'],
                        'BULK_SYM' => ['BULK'],
                        'CHALLENGE_RESPONSE_SYM' => ['CHALLENGE_RESPONSE'],
                        'CLONE_SYM' => ['CLONE'],
                        'COMPONENT_SYM' => ['COMPONENT'],
                        'CUME_DIST_SYM' => ['CUME_DIST'],
                        'DATETIME_SYM' => ['DATETIME'],
                        'DEFAULT_SYM' => ['DEFAULT'],
                        'DEFINITION_SYM' => ['DEFINITION'],
                        'DENSE_RANK_SYM' => ['DENSE_RANK'],
                        'DESCRIPTION_SYM' => ['DESCRIPTION'],
                        'DISCARD_SYM' => ['DISCARD'],
                        'EMPTY_SYM' => ['EMPTY'],
                        'ENFORCED_SYM' => ['ENFORCED'],
                        'ENGINE_ATTRIBUTE_SYM' => ['ENGINE_ATTRIBUTE'],
                        'ENUM_SYM' => ['ENUM'],
                        'EXCEPT_SYM' => ['EXCEPT'],
                        'EXCLUDE_SYM' => ['EXCLUDE'],
                        'FACTOR_SYM' => ['FACTOR'],
                        'FAILED_LOGIN_ATTEMPTS_SYM' => ['FAILED_LOGIN_ATTEMPTS'],
                        'FINISH_SYM' => ['FINISH'],
                        'FIRST_VALUE_SYM' => ['FIRST_VALUE'],
                        'FOLLOWING_SYM' => ['FOLLOWING'],
                        'GENERATE_SYM' => ['GENERATE'],
                        'GEOMETRYCOLLECTION_SYM' => ['GEOMCOLLECTION', 'GEOMETRYCOLLECTION'],
                        'GET_SOURCE_PUBLIC_KEY_SYM' => ['GET_SOURCE_PUBLIC_KEY'],
                        'GROUPING_SYM' => ['GROUPING'],
                        'GROUPS_SYM' => ['GROUPS'],
                        'GTID_ONLY_SYM' => ['GTID_ONLY'],
                        'HISTOGRAM_SYM' => ['HISTOGRAM'],
                        'HISTORY_SYM' => ['HISTORY'],
                        'INACTIVE_SYM' => ['INACTIVE'],
                        'INFILE_SYM' => ['INFILE'],
                        'INITIAL_SYM' => ['INITIAL'],
                        'INITIATE_SYM' => ['INITIATE'],
                        'INSERT_SYM' => ['INSERT'],
                        'INTERSECT_SYM' => ['INTERSECT'],
                        'INVISIBLE_SYM' => ['INVISIBLE'],
                        'JSON_TABLE_SYM' => ['JSON_TABLE'],
                        'JSON_VALUE_SYM' => ['JSON_VALUE'],
                        'KEYRING_SYM' => ['KEYRING'],
                        'LAG_SYM' => ['LAG'],
                        'LAST_VALUE_SYM' => ['LAST_VALUE'],
                        'LATERAL_SYM' => ['LATERAL'],
                        'LEAD_SYM' => ['LEAD'],
                        'LINESTRING_SYM' => ['LINESTRING'],
                        'LOCKED_SYM' => ['LOCKED'],
                        'LONGBLOB_SYM' => ['LONGBLOB'],
                        'LONGTEXT_SYM' => ['LONGTEXT'],
                        'MEDIUMBLOB_SYM' => ['MEDIUMBLOB'],
                        'MEDIUMINT_SYM' => ['INT3', 'MEDIUMINT', 'MIDDLEINT'],
                        'MEDIUMTEXT_SYM' => ['MEDIUMTEXT'],
                        'MEMBER_SYM' => ['MEMBER'],
                        'MULTILINESTRING_SYM' => ['MULTILINESTRING'],
                        'MULTIPOINT_SYM' => ['MULTIPOINT'],
                        'MULTIPOLYGON_SYM' => ['MULTIPOLYGON'],
                        'NESTED_SYM' => ['NESTED'],
                        'NETWORK_NAMESPACE_SYM' => ['NETWORK_NAMESPACE'],
                        'NOWAIT_SYM' => ['NOWAIT'],
                        'NTH_VALUE_SYM' => ['NTH_VALUE'],
                        'NTILE_SYM' => ['NTILE'],
                        'NULLS_SYM' => ['NULLS'],
                        'OFF_SYM' => ['OFF'],
                        'OF_SYM' => ['OF'],
                        'OJ_SYM' => ['OJ'],
                        'OLD_SYM' => ['OLD'],
                        'ON_SYM' => ['ON'],
                        'OPTIONAL_SYM' => ['OPTIONAL'],
                        'ORDINALITY_SYM' => ['ORDINALITY'],
                        'ORGANIZATION_SYM' => ['ORGANIZATION'],
                        'OTHERS_SYM' => ['OTHERS'],
                        'OUTER_SYM' => ['OUTER'],
                        'OVER_SYM' => ['OVER'],
                        'PASSWORD_LOCK_TIME_SYM' => ['PASSWORD_LOCK_TIME'],
                        'PATH_SYM' => ['PATH'],
                        'PERCENT_RANK_SYM' => ['PERCENT_RANK'],
                        'PERSIST_ONLY_SYM' => ['PERSIST_ONLY'],
                        'PERSIST_SYM' => ['PERSIST'],
                        'POLYGON_SYM' => ['POLYGON'],
                        'PRECEDING_SYM' => ['PRECEDING'],
                        'PRIVILEGE_CHECKS_USER_SYM' => ['PRIVILEGE_CHECKS_USER'],
                        'RANDOM_SYM' => ['RANDOM'],
                        'RANK_SYM' => ['RANK'],
                        'REAL_SYM' => ['REAL'],
                        'RECURSIVE_SYM' => ['RECURSIVE'],
                        'REFERENCE_SYM' => ['REFERENCE'],
                        'REGISTRATION_SYM' => ['REGISTRATION'],
                        'REPLACE_SYM' => ['REPLACE'],
                        'REPLICAS_SYM' => ['REPLICAS'],
                        'REPLICA_SYM' => ['REPLICA'],
                        'REQUIRE_ROW_FORMAT_SYM' => ['REQUIRE_ROW_FORMAT'],
                        'REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYM' => ['REQUIRE_TABLE_PRIMARY_KEY_CHECK'],
                        'RESOURCE_SYM' => ['RESOURCE'],
                        'RESPECT_SYM' => ['RESPECT'],
                        'RESTART_SYM' => ['RESTART'],
                        'RETAIN_SYM' => ['RETAIN'],
                        'RETURNING_SYM' => ['RETURNING'],
                        'REUSE_SYM' => ['REUSE'],
                        'ROLE_SYM' => ['ROLE'],
                        'ROW_NUMBER_SYM' => ['ROW_NUMBER'],
                        'SECONDARY_ENGINE_ATTRIBUTE_SYM' => ['SECONDARY_ENGINE_ATTRIBUTE'],
                        'SECONDARY_ENGINE_SYM' => ['SECONDARY_ENGINE'],
                        'SECONDARY_LOAD_SYM' => ['SECONDARY_LOAD'],
                        'SECONDARY_SYM' => ['SECONDARY'],
                        'SECONDARY_UNLOAD_SYM' => ['SECONDARY_UNLOAD'],
                        'SET_SYM' => ['SET'],
                        'SKIP_SYM' => ['SKIP'],
                        'SMALLINT_SYM' => ['INT2', 'SMALLINT'],
                        'SOURCE_AUTO_POSITION_SYM' => ['SOURCE_AUTO_POSITION'],
                        'SOURCE_BIND_SYM' => ['SOURCE_BIND'],
                        'SOURCE_COMPRESSION_ALGORITHM_SYM' => ['SOURCE_COMPRESSION_ALGORITHMS'],
                        'SOURCE_CONNECTION_AUTO_FAILOVER_SYM' => ['SOURCE_CONNECTION_AUTO_FAILOVER'],
                        'SOURCE_CONNECT_RETRY_SYM' => ['SOURCE_CONNECT_RETRY'],
                        'SOURCE_DELAY_SYM' => ['SOURCE_DELAY'],
                        'SOURCE_HEARTBEAT_PERIOD_SYM' => ['SOURCE_HEARTBEAT_PERIOD'],
                        'SOURCE_HOST_SYM' => ['SOURCE_HOST'],
                        'SOURCE_LOG_FILE_SYM' => ['SOURCE_LOG_FILE'],
                        'SOURCE_LOG_POS_SYM' => ['SOURCE_LOG_POS'],
                        'SOURCE_PASSWORD_SYM' => ['SOURCE_PASSWORD'],
                        'SOURCE_PORT_SYM' => ['SOURCE_PORT'],
                        'SOURCE_PUBLIC_KEY_PATH_SYM' => ['SOURCE_PUBLIC_KEY_PATH'],
                        'SOURCE_RETRY_COUNT_SYM' => ['SOURCE_RETRY_COUNT'],
                        'SOURCE_SSL_CAPATH_SYM' => ['SOURCE_SSL_CAPATH'],
                        'SOURCE_SSL_CA_SYM' => ['SOURCE_SSL_CA'],
                        'SOURCE_SSL_CERT_SYM' => ['SOURCE_SSL_CERT'],
                        'SOURCE_SSL_CIPHER_SYM' => ['SOURCE_SSL_CIPHER'],
                        'SOURCE_SSL_CRLPATH_SYM' => ['SOURCE_SSL_CRLPATH'],
                        'SOURCE_SSL_CRL_SYM' => ['SOURCE_SSL_CRL'],
                        'SOURCE_SSL_KEY_SYM' => ['SOURCE_SSL_KEY'],
                        'SOURCE_SSL_SYM' => ['SOURCE_SSL'],
                        'SOURCE_SSL_VERIFY_SERVER_CERT_SYM' => ['SOURCE_SSL_VERIFY_SERVER_CERT'],
                        'SOURCE_TLS_CIPHERSUITES_SYM' => ['SOURCE_TLS_CIPHERSUITES'],
                        'SOURCE_TLS_VERSION_SYM' => ['SOURCE_TLS_VERSION'],
                        'SOURCE_USER_SYM' => ['SOURCE_USER'],
                        'SOURCE_ZSTD_COMPRESSION_LEVEL_SYM' => ['SOURCE_ZSTD_COMPRESSION_LEVEL'],
                        'SRID_SYM' => ['SRID'],
                        'STREAM_SYM' => ['STREAM'],
                        'SYSTEM_SYM' => ['SYSTEM'],
                        'THREAD_PRIORITY_SYM' => ['THREAD_PRIORITY'],
                        'TIES_SYM' => ['TIES'],
                        'TIMESTAMP_SYM' => ['TIMESTAMP'],
                        'TINYBLOB_SYM' => ['TINYBLOB'],
                        'TINYINT_SYM' => ['INT1', 'TINYINT'],
                        'TINYTEXT_SYN' => ['TINYTEXT'],
                        'TLS_SYM' => ['TLS'],
                        'UNBOUNDED_SYM' => ['UNBOUNDED'],
                        'UNREGISTER_SYM' => ['UNREGISTER'],
                        'UNSIGNED_SYM' => ['UNSIGNED'],
                        'URL_SYM' => ['URL'],
                        'VARBINARY_SYM' => ['VARBINARY'],
                        'VARCHAR_SYM' => ['VARCHAR', 'VARCHARACTER'],
                        'VCPU_SYM' => ['VCPU'],
                        'VISIBLE_SYM' => ['VISIBLE'],
                        'WINDOW_SYM' => ['WINDOW'],
                        'ZEROFILL_SYM' => ['ZEROFILL'],
                        'ZONE_SYM' => ['ZONE'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0' => [
                        'MASTER_AUTO_POSITION_SYM' => ['MASTER_AUTO_POSITION'],
                        'MASTER_BIND_SYM' => ['MASTER_BIND'],
                        'MASTER_CONNECT_RETRY_SYM' => ['MASTER_CONNECT_RETRY'],
                        'MASTER_DELAY_SYM' => ['MASTER_DELAY'],
                        'MASTER_HEARTBEAT_PERIOD_SYM' => ['MASTER_HEARTBEAT_PERIOD'],
                        'MASTER_HOST_SYM' => ['MASTER_HOST'],
                        'MASTER_LOG_FILE_SYM' => ['MASTER_LOG_FILE'],
                        'MASTER_LOG_POS_SYM' => ['MASTER_LOG_POS'],
                        'MASTER_PASSWORD_SYM' => ['MASTER_PASSWORD'],
                        'MASTER_PORT_SYM' => ['MASTER_PORT'],
                        'MASTER_RETRY_COUNT_SYM' => ['MASTER_RETRY_COUNT'],
                        'MASTER_SSL_CAPATH_SYM' => ['MASTER_SSL_CAPATH'],
                        'MASTER_SSL_CA_SYM' => ['MASTER_SSL_CA'],
                        'MASTER_SSL_CERT_SYM' => ['MASTER_SSL_CERT'],
                        'MASTER_SSL_CIPHER_SYM' => ['MASTER_SSL_CIPHER'],
                        'MASTER_SSL_CRLPATH_SYM' => ['MASTER_SSL_CRLPATH'],
                        'MASTER_SSL_CRL_SYM' => ['MASTER_SSL_CRL'],
                        'MASTER_SSL_KEY_SYM' => ['MASTER_SSL_KEY'],
                        'MASTER_SSL_SYM' => ['MASTER_SSL'],
                        'MASTER_SSL_VERIFY_SERVER_CERT_SYM' => ['MASTER_SSL_VERIFY_SERVER_CERT'],
                        'MASTER_USER_SYM' => ['MASTER_USER'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'PARSE_TREE_SYM' => ['PARSE_TREE'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0' => [
                        'MASTER_TLS_VERSION_SYM' => ['MASTER_TLS_VERSION'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'GTIDS_SYM' => ['GTIDS'],
                        'LOG_SYM' => ['LOG'],
                        'PARALLEL_SYM' => ['PARALLEL'],
                        'S3_SYM' => ['S3'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0' => [
                        'GET_MASTER_PUBLIC_KEY_SYM' => ['GET_MASTER_PUBLIC_KEY'],
                        'MASTER_COMPRESSION_ALGORITHM_SYM' => ['MASTER_COMPRESSION_ALGORITHMS'],
                        'MASTER_PUBLIC_KEY_PATH_SYM' => ['MASTER_PUBLIC_KEY_PATH'],
                        'MASTER_TLS_CIPHERSUITES_SYM' => ['MASTER_TLS_CIPHERSUITES'],
                        'MASTER_ZSTD_COMPRESSION_LEVEL_SYM' => ['MASTER_ZSTD_COMPRESSION_LEVEL'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'QUALIFY_SYM' => ['QUALIFY'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'AUTO_SYM' => ['AUTO'],
                        'BERNOULLI_SYM' => ['BERNOULLI'],
                        'MANUAL_SYM' => ['MANUAL'],
                        'TABLESAMPLE_SYM' => ['TABLESAMPLE'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.6.51', 'mysql-5.7.44' => [
                        'ANALYSE_SYM' => ['ANALYSE'],
                        'BIGINT' => ['BIGINT', 'INT8'],
                        'BINARY' => ['BINARY'],
                        'DATETIME' => ['DATETIME'],
                        'DEFAULT' => ['DEFAULT'],
                        'DES_KEY_FILE' => ['DES_KEY_FILE'],
                        'DISCARD' => ['DISCARD'],
                        'ENUM' => ['ENUM'],
                        'GEOMETRYCOLLECTION' => ['GEOMETRYCOLLECTION'],
                        'INFILE' => ['INFILE'],
                        'INSERT' => ['INSERT'],
                        'LINESTRING' => ['LINESTRING'],
                        'LONGBLOB' => ['LONGBLOB'],
                        'LONGTEXT' => ['LONGTEXT'],
                        'MASTER_SERVER_ID_SYM' => ['MASTER_SERVER_ID'],
                        'MEDIUMBLOB' => ['MEDIUMBLOB'],
                        'MEDIUMINT' => ['INT3', 'MEDIUMINT', 'MIDDLEINT'],
                        'MEDIUMTEXT' => ['MEDIUMTEXT'],
                        'MULTILINESTRING' => ['MULTILINESTRING'],
                        'MULTIPOINT' => ['MULTIPOINT'],
                        'MULTIPOLYGON' => ['MULTIPOLYGON'],
                        'ON' => ['ON'],
                        'OUTER' => ['OUTER'],
                        'POLYGON' => ['POLYGON'],
                        'REAL' => ['REAL'],
                        'REDOFILE_SYM' => ['REDOFILE'],
                        'REPLACE' => ['REPLACE'],
                        'SET' => ['SET'],
                        'SMALLINT' => ['INT2', 'SMALLINT'],
                        'SQL_CACHE_SYM' => ['SQL_CACHE'],
                        'TIMESTAMP' => ['TIMESTAMP'],
                        'TINYBLOB' => ['TINYBLOB'],
                        'TINYINT' => ['INT1', 'TINYINT'],
                        'TINYTEXT' => ['TINYTEXT'],
                        'UNSIGNED' => ['UNSIGNED'],
                        'VARBINARY' => ['VARBINARY'],
                        'VARCHAR' => ['VARCHAR', 'VARCHARACTER'],
                        'ZEROFILL' => ['ZEROFILL'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-9.0.1', 'mysql-9.1.0' => [
                        'VECTOR_SYM' => ['VECTOR'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.6.51' => [
                        'OLD_PASSWORD' => ['OLD_PASSWORD'],
                        'TABLESPACE' => ['TABLESPACE'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.7.44' => [
                        'PARSE_GCOL_EXPR_SYM' => ['PARSE_GCOL_EXPR'],
                    ],
                    default => [],
                },
            ],
            'functions' => [
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                'ADDDATE_SYM' => ['ADDDATE'],
                'CAST_SYM' => ['CAST'],
                'COUNT_SYM' => ['COUNT'],
                'CURDATE' => ['CURDATE'],
                'CURTIME' => ['CURTIME'],
                'DATE_ADD_INTERVAL' => ['DATE_ADD'],
                'DATE_SUB_INTERVAL' => ['DATE_SUB'],
                'EXTRACT_SYM' => ['EXTRACT'],
                'GROUP_CONCAT_SYM' => ['GROUP_CONCAT'],
                'MAX_SYM' => ['MAX'],
                'MIN_SYM' => ['MIN'],
                'NOW_SYM' => ['NOW'],
                'POSITION_SYM' => ['POSITION'],
                'STDDEV_SAMP_SYM' => ['STDDEV_SAMP'],
                'STD_SYM' => ['STD', 'STDDEV', 'STDDEV_POP'],
                'SUBDATE_SYM' => ['SUBDATE'],
                'SUBSTRING' => ['MID', 'SUBSTR', 'SUBSTRING'],
                'SUM_SYM' => ['SUM'],
                'SYSDATE' => ['SYSDATE'],
                'TRIM' => ['TRIM'],
                'USER' => ['SESSION_USER', 'SYSTEM_USER'],
                'VARIANCE_SYM' => ['VARIANCE', 'VAR_POP'],
                'VAR_SAMP_SYM' => ['VAR_SAMP'],
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'JSON_ARRAYAGG' => ['JSON_ARRAYAGG'],
                        'JSON_OBJECTAGG' => ['JSON_OBJECTAGG'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.1.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.2.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.3.0/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.0.1/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0' => [
                        'BIT_AND_SYM' => ['BIT_AND'],
                        'BIT_OR_SYM' => ['BIT_OR'],
                        'BIT_XOR_SYM' => ['BIT_XOR'],
                        'ST_COLLECT_SYM' => ['ST_COLLECT'],
                    ],
                    default => [],
                },
                /**
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/lex.h
                 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h
                 */
                ...match ($version) {
                    'mysql-5.6.51', 'mysql-5.7.44' => [
                        'BIT_AND' => ['BIT_AND'],
                        'BIT_OR' => ['BIT_OR'],
                        'BIT_XOR' => ['BIT_XOR'],
                    ],
                    default => [],
                },
            ],
        ];
        return new LexicalDefinition(
            version: $version,
            dialect: 'MySQL',
            lexemes: new ChoiceLexemeGenerator(
                new VersionedLexemeGenerator(
                    $version,
                    new VersionCase(
                        ['mysql-5.7.44'],
                        new ChoiceLexemeGenerator(
                            new ValueLexemeGenerator(
                                'ROTATE_KEY_ENGINE',
                                new WordDomain(['INNODB'], true),
                                ['INNODB'],
                                'identifier',
                                'sql/sql_yacc.yy:alter_instance_action',
                            ),
                            new ReplicationTablePatternLexemeGenerator(),
                        ),
                        'mysql-5.7-key-rotation',
                    ),
                    new VersionCase(
                        ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                        new ChoiceLexemeGenerator(
                            new ReplicationTablePatternLexemeGenerator(),
                            new FactorLexemeGenerator(),
                            new ValueLexemeGenerator(
                                'EXTERNAL_ROUTINE_LANGUAGE',
                                new IdentifierDomain(
                                    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_',
                                    excluded: ['SQL'],
                                ),
                                ['javascript'],
                                'identifier',
                                'sql/sql_yacc.yy:stored_routine_body',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'BINLOG_RESET_INDEX',
                                1,
                                2000000000,
                                ['1', '2000000000', "X'01'"],
                                'sql/sql_yacc.yy:source_reset_options',
                            ),
                            new ValueLexemeGenerator(
                                'ROTATE_KEY_ENGINE',
                                new WordDomain(['INNODB', 'BINLOG'], true),
                                ['INNODB', 'BINLOG'],
                                'identifier',
                                'sql/sql_yacc.yy:alter_instance_action',
                            ),
                            new ValueLexemeGenerator(
                                'REDO_ENGINE',
                                new WordDomain(['INNODB'], true),
                                ['INNODB'],
                                'identifier',
                                'sql/sql_yacc.yy:alter_instance_action',
                            ),
                            new ValueLexemeGenerator(
                                'REDO_LOG_NAME',
                                new WordDomain(['REDO_LOG'], true),
                                ['REDO_LOG'],
                                'identifier',
                                'sql/sql_yacc.yy:alter_instance_action',
                            ),
                            new ValueLexemeGenerator(
                                'LOAD_COUNT_NAME',
                                new WordDomain(['COUNT'], true),
                                ['COUNT'],
                                'identifier',
                                'sql/sql_yacc.yy:opt_source_count',
                            ),
                            new IntegerLexemeGenerator(
                                'LOAD_SOURCE_COUNT',
                                '1',
                                '2147483647',
                                ['1'],
                                'sql/sql_yacc.yy:opt_source_count',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'REPLICATION_FLAG_NUMBER',
                                0,
                                1,
                                ['0', '1'],
                                'sql/sql_yacc.yy:SOURCE_CONNECTION_AUTO_FAILOVER',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'TERNARY_OPTION_NUMBER',
                                0,
                                1,
                                ['0', '1'],
                                'sql/sql_yacc.yy:ternary_option',
                            ),
                        ),
                        'mysql-8-and-9-instance-actions',
                    ),
                ),
                new KeywordLexemeGenerator(
                    $keywords['symbols'],
                    $keywords['functions'],
                ),
                new VersionedLexemeGenerator(
                    $version,
                    new VersionCase(
                        ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                        new ChoiceLexemeGenerator(
                            new SizeNumberLexemeGenerator(),
                            new PrecisionLexemeGenerator(),
                            new BoundedIntegerLexemeGenerator(
                                'WEIGHT_STRING_LENGTH',
                                1,
                                2147483647,
                                ['1', '2147483647'],
                                'sql/sql_yacc.yy:ws_num_codepoints',
                            ),
                            new IntegerLexemeGenerator(
                                'NUMERIC_SCALE_NUMBER',
                                '0',
                                '30',
                                ['0', '1', '30'],
                                'sql/create_field.cc:Create_field::init:decimals',
                            ),
                            new IntegerLexemeGenerator(
                                'DISPLAY_WIDTH_NUMBER',
                                '0',
                                '255',
                                ['0', '1', '255'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new IntegerLexemeGenerator(
                                'BIT_WIDTH_NUMBER',
                                '1',
                                '64',
                                ['1', '64'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new IntegerLexemeGenerator(
                                'DECIMAL_PRECISION_NUMBER',
                                '0',
                                '65',
                                ['0', '1', '65'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new IntegerLexemeGenerator(
                                'FLOAT_PRECISION_NUMBER',
                                '0',
                                '53',
                                ['0', '24', '53'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new IntegerLexemeGenerator(
                                'VARCHAR_LENGTH_NUMBER',
                                '0',
                                '65535',
                                ['0', '1', '65535'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new IntegerLexemeGenerator(
                                'FIELD_LENGTH_NUMBER',
                                '0',
                                '4294967295',
                                ['0', '1', '4294967295'],
                                'sql/create_field.cc:Create_field::init',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'KEY_ALGORITHM_NUMBER',
                                1,
                                2,
                                ['1', '2'],
                                'sql/sql_yacc.yy:opt_key_algo',
                            ),
                            new IntegerLexemeGenerator(
                                'PARTITION_COUNT_NUMBER',
                                '1',
                                '4294967295',
                                ['1', '2', '4294967295'],
                                'sql/sql_yacc.yy:opt_num_parts',
                            ),
                            new IntegerLexemeGenerator(
                                'AVG_ROW_LENGTH_NUMBER',
                                '0',
                                '4294967295',
                                ['0', '1', '4294967295'],
                                'sql/sql_yacc.yy:AVG_ROW_LENGTH',
                            ),
                            new IntegerLexemeGenerator(
                                'YEAR_WIDTH_NUMBER',
                                '4',
                                '4',
                                ['4'],
                                'sql/sql_yacc.yy:YEAR_SYM',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'KEY_BLOCK_SIZE_NUMBER',
                                0,
                                65535,
                                ['0', '1', '65535'],
                                'sql/sql_yacc.yy:KEY_BLOCK_SIZE',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'STATS_SAMPLE_PAGES_NUMBER',
                                1,
                                65535,
                                ['1', '65535'],
                                'sql/sql_yacc.yy:STATS_SAMPLE_PAGES',
                            ),
                            new BoundedIntegerLexemeGenerator(
                                'SOURCE_DELAY_NUMBER',
                                0,
                                2147483647,
                                ['0', '1', '2147483647'],
                                'sql/sql_yacc.yy:SOURCE_DELAY',
                            ),
                            new ChoiceLexemeGenerator(
                                new ChoiceLexemeGenerator(
                                    new ValueLexemeGenerator(
                                        'IDENT',
                                        new IdentifierDomain(
                                            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_$',
                                            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                            '_sf',
                                            63,
                                        ),
                                        ['_sqlfaker_identifier'],
                                        'identifier',
                                        'sql/sql_lex.cc:MY_LEX_IDENT',
                                    ),
                                    new ValueLexemeGenerator(
                                        'IDENT_QUOTED',
                                        new QuotedDomain(
                                            '`',
                                            minimum: 1,
                                            maximum: 64,
                                            trailingSpace: false,
                                        ),
                                        ['`name`'],
                                        'quoted-identifier',
                                        'sql/sql_lex.cc:MY_LEX_USER_VARIABLE_DELIMITER',
                                    ),
                                    new ValueLexemeGenerator(
                                        'LEX_HOSTNAME',
                                        new CharacterDomain(
                                            [
                                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F',
                                                'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
                                                'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '_', '.',
                                                '$',
                                            ],
                                            1,
                                            64,
                                        ),
                                        ['localhost'],
                                        'hostname',
                                        'sql/sql_lex.cc:MY_LEX_HOSTNAME',
                                    ),
                                    new CharsetLexemeGenerator(),
                                ),
                                new ChoiceLexemeGenerator(
                                    new CharsetValueLexemeGenerator(
                                        new ValueLexemeGenerator(
                                            'TEXT_STRING',
                                            new QuotedDomain(
                                                "'",
                                                backslash: true,
                                                alphabet: [
                                                    "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                    "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                    '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0',
                                                    '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@',
                                                    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                                                    'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`',
                                                    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                    'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f", 'é',
                                                    '猫',
                                                ],
                                            ),
                                            ["'text'", "'a''b'"],
                                            'string',
                                            'sql/sql_lex.cc:get_text',
                                        ),
                                        new ValueLexemeGenerator(
                                            'TEXT_STRING',
                                            new QuotedDomain(
                                                "'",
                                                backslash: true,
                                                alphabet: [
                                                    "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                    "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                    '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0',
                                                    '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@',
                                                    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                                                    'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`',
                                                    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                    'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f",
                                                ],
                                            ),
                                            ["'text'", "'a''b'"],
                                            'string',
                                            'sql/sql_lex.cc:get_text',
                                        ),
                                    ),
                                    new ValueLexemeGenerator(
                                        'NCHAR_STRING',
                                        new QuotedDomain(
                                            "'",
                                            ['N', 'n'],
                                            true,
                                            alphabet: [
                                                "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0',
                                                '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@',
                                                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                                                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`',
                                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f", 'é',
                                                '猫',
                                            ],
                                        ),
                                        ["N'text'"],
                                        'string',
                                        'sql/sql_lex.cc:MY_LEX_IDENT_OR_NCHAR',
                                    ),
                                ),
                                new ChoiceLexemeGenerator(
                                    new IntegerLexemeGenerator(
                                        'NUM',
                                        '0',
                                        '2147483647',
                                        ['1', '0', '2'],
                                        'sql/sql_lex.cc:int_token:NUM',
                                    ),
                                    new IntegerLexemeGenerator(
                                        'LONG_NUM',
                                        '2147483648',
                                        '9223372036854775807',
                                        ['2147483648'],
                                        'sql/sql_lex.cc:int_token:LONG_NUM',
                                    ),
                                    new IntegerLexemeGenerator(
                                        'ULONGLONG_NUM',
                                        '9223372036854775808',
                                        '18446744073709551615',
                                        ['18446744073709551615'],
                                        'sql/sql_lex.cc:int_token:ULONGLONG_NUM',
                                    ),
                                    new IntegerLexemeGenerator(
                                        'DECIMAL_NUM',
                                        '18446744073709551616',
                                        null,
                                        ['18446744073709551616'],
                                        'sql/sql_lex.cc:int_token:DECIMAL_NUM',
                                    ),
                                    new ValueLexemeGenerator(
                                        'DECIMAL_NUM',
                                        new ChoiceDomain(
                                            new SequenceDomain(
                                                new CharacterDomain(
                                                    ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                    1,
                                                    20,
                                                ),
                                                new WordDomain(['.']),
                                                new CharacterDomain(
                                                    ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                    0,
                                                    30,
                                                ),
                                            ),
                                            new SequenceDomain(
                                                new WordDomain(['.']),
                                                new CharacterDomain(
                                                    ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                    1,
                                                    20,
                                                ),
                                            ),
                                        ),
                                        ['1.5'],
                                        'number',
                                        'sql/sql_lex.cc:MY_LEX_REAL',
                                    ),
                                    new ValueLexemeGenerator(
                                        'FLOAT_NUM',
                                        new SequenceDomain(
                                            new ChoiceDomain(
                                                new CharacterDomain(
                                                    ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                    1,
                                                    20,
                                                ),
                                                new ChoiceDomain(
                                                    new SequenceDomain(
                                                        new CharacterDomain(
                                                            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                            1,
                                                            20,
                                                        ),
                                                        new WordDomain(['.']),
                                                        new CharacterDomain(
                                                            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                            0,
                                                            30,
                                                        ),
                                                    ),
                                                    new SequenceDomain(
                                                        new WordDomain(['.']),
                                                        new CharacterDomain(
                                                            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                            1,
                                                            20,
                                                        ),
                                                    ),
                                                ),
                                            ),
                                            new SequenceDomain(
                                                new WordDomain(['e', 'E']),
                                                new WordDomain(['', '+', '-']),
                                                new CharacterDomain(
                                                    ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                    1,
                                                    3,
                                                ),
                                            ),
                                        ),
                                        ['1e2'],
                                        'number',
                                        'sql/sql_lex.cc:MY_LEX_REAL:exponent',
                                    ),
                                ),
                                new CharsetValueLexemeGenerator(
                                    new ChoiceLexemeGenerator(
                                        new ValueLexemeGenerator(
                                            'HEX_NUM',
                                            new RadixDomain(
                                                '0123456789abcdefABCDEF',
                                                '0x',
                                                ['X', 'x'],
                                                2,
                                                32,
                                                false,
                                            ),
                                            [sprintf('0x%02x', 15), "X'0f'"],
                                            'number',
                                            'sql/sql_lex.cc:MY_LEX_HEX_NUMBER',
                                        ),
                                        new ValueLexemeGenerator(
                                            'BIN_NUM',
                                            new RadixDomain(
                                                '01',
                                                '0b',
                                                ['B', 'b'],
                                                1,
                                                64,
                                                false,
                                            ),
                                            ['0b01', "B'01'"],
                                            'number',
                                            'sql/sql_lex.cc:MY_LEX_BIN_NUMBER',
                                        ),
                                    ),
                                    new ChoiceLexemeGenerator(
                                        new ValueLexemeGenerator(
                                            'HEX_NUM',
                                            new RadixDomain(
                                                '0123456789abcdefABCDEF',
                                                '0x',
                                                ['X', 'x'],
                                                2,
                                                32,
                                                true,
                                            ),
                                            [sprintf('0x%02x', 15), "X'0f'"],
                                            'number',
                                            'sql/sql_lex.cc:MY_LEX_HEX_NUMBER',
                                        ),
                                        new ValueLexemeGenerator(
                                            'BIN_NUM',
                                            new RadixDomain(
                                                '01',
                                                '0b',
                                                ['B', 'b'],
                                                1,
                                                64,
                                                true,
                                            ),
                                            ['0b01', "B'01'"],
                                            'number',
                                            'sql/sql_lex.cc:MY_LEX_BIN_NUMBER',
                                        ),
                                    ),
                                ),
                            ),
                            new VersionedLexemeGenerator(
                                $version,
                                new VersionCase(
                                    ['mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                                    new ValueLexemeGenerator(
                                        'DOLLAR_QUOTED_STRING_SYM',
                                        new DollarQuotedDomain(
                                            new CharacterDomain(
                                                [
                                                    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                    'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F',
                                                    'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
                                                    'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '_', 'é',
                                                    '猫',
                                                ],
                                                0,
                                                16,
                                            ),
                                            new CharacterDomain(
                                                [
                                                    "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                    "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                    '!', '"', '#', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0', '1',
                                                    '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@', 'A',
                                                    'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q',
                                                    'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`', 'a',
                                                    'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q',
                                                    'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f", 'é', '猫',
                                                ],
                                                0,
                                                255,
                                            ),
                                        ),
                                        ['$$text$$', '$tag$text$tag$'],
                                        'string',
                                        'sql/sql_lex.cc:get_dollar_quoted_text',
                                    ),
                                    'mysql-dollar-quoted-strings',
                                ),
                            ),
                            new ChoiceLexemeGenerator(
                                new MatchingLexemeGenerator(
                                    '(',
                                    new FixedLexemeGenerator('(', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    ')',
                                    new FixedLexemeGenerator(')', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '[',
                                    new FixedLexemeGenerator('[', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    ']',
                                    new FixedLexemeGenerator(']', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    ',',
                                    new FixedLexemeGenerator(',', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '.',
                                    new FixedLexemeGenerator('.', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    ';',
                                    new FixedLexemeGenerator(';', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    ':',
                                    new FixedLexemeGenerator(':', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '+',
                                    new FixedLexemeGenerator('+', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '-',
                                    new FixedLexemeGenerator('-', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '*',
                                    new FixedLexemeGenerator('*', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '/',
                                    new FixedLexemeGenerator('/', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '%',
                                    new FixedLexemeGenerator('%', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '=',
                                    new FixedLexemeGenerator('=', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '<',
                                    new FixedLexemeGenerator('<', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '>',
                                    new FixedLexemeGenerator('>', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '!',
                                    new FixedLexemeGenerator('!', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '|',
                                    new FixedLexemeGenerator('|', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '&',
                                    new FixedLexemeGenerator('&', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '^',
                                    new FixedLexemeGenerator('^', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '~',
                                    new FixedLexemeGenerator('~', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '@',
                                    new FixedLexemeGenerator('@', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '{',
                                    new FixedLexemeGenerator('{', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    '}',
                                    new FixedLexemeGenerator('}', 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    'PARAM_MARKER',
                                    new FixedLexemeGenerator('?', 'symbol', 'sql/sql_lex.cc:PARAM_MARKER'),
                                ),
                                new MatchingLexemeGenerator(
                                    'SET_VAR',
                                    new FixedLexemeGenerator(':=', 'symbol', 'sql/sql_lex.cc:SET_VAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    'OR2_SYM',
                                    new FixedLexemeGenerator('||', 'symbol', 'sql/sql_lex.cc:OR2_SYM'),
                                ),
                                new MatchingLexemeGenerator(
                                    'NOT2_SYM',
                                    new FixedLexemeGenerator('NOT', 'symbol', 'sql/sql_lex.cc:NOT2_SYM'),
                                ),
                                new MatchingLexemeGenerator(
                                    'CONCAT_FUNCTION_NAME',
                                    new FixedLexemeGenerator('CONCAT', 'function', 'sql/sql_yacc.yy:simple_expr:Item_func_concat'),
                                ),
                                new VersionedLexemeGenerator(
                                    $version,
                                    new VersionCase(
                                        ['mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                                        new ChoiceLexemeGenerator(
                                            new MatchingLexemeGenerator(
                                                'JSON_SEPARATOR_SYM',
                                                new FixedLexemeGenerator('->', 'operator', 'sql/sql_lex.cc:MY_LEX_CHAR:json'),
                                            ),
                                            new MatchingLexemeGenerator(
                                                'JSON_UNQUOTED_SEPARATOR_SYM',
                                                new FixedLexemeGenerator('->>', 'operator', 'sql/sql_lex.cc:MY_LEX_CHAR:json-unquoted'),
                                            ),
                                        ),
                                        'mysql-json-operators',
                                    ),
                                ),
                                new ChoiceLexemeGenerator(
                                    new MatchingLexemeGenerator(
                                        'WITH_ROLLUP_SYM',
                                        new SequenceLexemeGenerator(
                                            new FixedLexemeGenerator('WITH', 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
                                            new FixedLexemeGenerator('ROLLUP', 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
                                        ),
                                    ),
                                    new MatchingLexemeGenerator(
                                        'WITH_CUBE_SYM',
                                        new VersionedLexemeGenerator(
                                            $version,
                                            new VersionCase(
                                                ['mysql-5.6.51', 'mysql-5.7.44'],
                                                new SequenceLexemeGenerator(
                                                    new FixedLexemeGenerator('WITH', 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
                                                    new FixedLexemeGenerator('CUBE', 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
                                                ),
                                                'mysql-with-cube',
                                            ),
                                        ),
                                    ),
                                ),
                                new ChoiceLexemeGenerator(
                                    new MatchingLexemeGenerator(
                                        'END_OF_INPUT',
                                        new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:MY_LEX_EOL'),
                                    ),
                                    new VersionedLexemeGenerator(
                                        $version,
                                        new VersionCase(
                                            ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                                            new ChoiceLexemeGenerator(
                                                new MatchingLexemeGenerator(
                                                    'GRAMMAR_SELECTOR_CTE',
                                                    new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'),
                                                ),
                                                new MatchingLexemeGenerator(
                                                    'GRAMMAR_SELECTOR_DERIVED_EXPR',
                                                    new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'),
                                                ),
                                                new MatchingLexemeGenerator(
                                                    'GRAMMAR_SELECTOR_EXPR',
                                                    new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'),
                                                ),
                                                new MatchingLexemeGenerator(
                                                    'GRAMMAR_SELECTOR_GCOL',
                                                    new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'),
                                                ),
                                                new MatchingLexemeGenerator(
                                                    'GRAMMAR_SELECTOR_PART',
                                                    new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'),
                                                ),
                                            ),
                                            'mysql-parser-selectors',
                                        ),
                                    ),
                                ),
                            ),
                        ),
                        'mysql-default-mode-scanner',
                    ),
                ),
            ),
            spacing: new CombinedSpacingRule(
                new FunctionSpacingRule(),
                new VariableSpacingRule(),
                new QualifiedNameSpacingRule(),
                new CloneAddressSpacingRule(),
                new KeywordPhraseSpacingRule(),
            ),
            keywords: $keywords['symbols'],
            functions: $keywords['functions'],
            nonOutput: ['END_OF_INPUT', 'GRAMMAR_SELECTOR_CTE', 'GRAMMAR_SELECTOR_DERIVED_EXPR', 'GRAMMAR_SELECTOR_EXPR', 'GRAMMAR_SELECTOR_GCOL', 'GRAMMAR_SELECTOR_PART'],
            dollarVersions: ['mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
        );
    }
}
