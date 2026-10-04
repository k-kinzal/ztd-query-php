<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

/**
 * The result row layouts of SHOW and EXPLAIN; each case holds the key of its column table.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
enum Report: string
{
    case Databases = 'databases';
    case Tables = 'tables';
    case FullTables = 'tables_full';
    case Triggers = 'triggers';
    case Events = 'events';
    case TableStatus = 'table_status';
    case OpenTables = 'open_tables';
    case Columns = 'columns';
    case FullColumns = 'columns_full';
    case Keys = 'keys';
    case CreateDatabase = 'create_database';
    case CreateTable = 'create_table';
    case CreateView = 'create_view';
    case CreateProcedure = 'create_procedure';
    case CreateFunction = 'create_function';
    case CreateTrigger = 'create_trigger';
    case CreateEvent = 'create_event';
    case RoutineStatus = 'routine_status';
    case RoutineCode = 'routine_code';
    case Plugins = 'plugins';
    case Engine = 'engine';
    case Engines = 'engines';
    case WarningCount = 'warning_count';
    case ErrorCount = 'error_count';
    case Diagnostics = 'diagnostics';
    case Profiles = 'profiles';
    case Profile = 'profile';
    case Variables = 'variables';
    case Processlist = 'processlist';
    case FullProcesslist = 'processlist_full';
    case Charsets = 'charset';
    case Collations = 'collation';
    case Privileges = 'privileges';
    case Grants = 'grants';
    case CreateUser = 'create_user';
    case BinaryLogs = 'binary_logs';
    case ReplicaHosts = 'replica_hosts';
    case Replicas = 'replicas';
    case LogEvents = 'log_events';
    case LogStatus = 'log_status';
    case SlaveStatus = 'slave_status';
    case ReplicaStatus = 'replica_status';
    case Explain = 'explain';
    case ExplainExtended = 'explain_extended';
    case ExplainPartitions = 'explain_partitions';
    case ExplainDocument = 'explain_document';
}
