<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Release-specific privilege definitions used by SHOW and INFORMATION_SCHEMA.
 * Shared definitions and explicit release changes have one owner in the implementation.
 *
 * @visibility MySqlMemory
 */
final class PrivilegeDefinitions
{
    /**
     * @var array<string, array{string, string, string}> Common definitions indexed by name
     */
    private const COMMON = [
        'Alter' => ['Alter', 'Tables', 'To alter the table'],
        'Alter routine' => ['Alter routine', 'Functions,Procedures', 'To alter or drop stored functions/procedures'],
        'Create' => ['Create', 'Databases,Tables,Indexes', 'To create new databases and tables'],
        'Create routine' => ['Create routine', 'Databases', 'To use CREATE FUNCTION/PROCEDURE'],
        'Create role' => ['Create role', 'Server Admin', 'To create new roles'],
        'Create temporary tables' => ['Create temporary tables', 'Databases', 'To use CREATE TEMPORARY TABLE'],
        'Create view' => ['Create view', 'Tables', 'To create new views'],
        'Create user' => ['Create user', 'Server Admin', 'To create new users'],
        'Delete' => ['Delete', 'Tables', 'To delete existing rows'],
        'Drop' => ['Drop', 'Databases,Tables', 'To drop databases, tables, and views'],
        'Drop role' => ['Drop role', 'Server Admin', 'To drop roles'],
        'Event' => ['Event', 'Server Admin', 'To create, alter, drop and execute events'],
        'Execute' => ['Execute', 'Functions,Procedures', 'To execute stored routines'],
        'File' => ['File', 'File access on server', 'To read and write files on the server'],
        'Grant option' => ['Grant option', 'Databases,Tables,Functions,Procedures', 'To give to other users those privileges you possess'],
        'Index' => ['Index', 'Tables', 'To create or drop indexes'],
        'Insert' => ['Insert', 'Tables', 'To insert data into tables'],
        'Lock tables' => ['Lock tables', 'Databases', 'To use LOCK TABLES (together with SELECT privilege)'],
        'Process' => ['Process', 'Server Admin', 'To view the plain text of currently executing queries'],
        'Proxy' => ['Proxy', 'Server Admin', 'To make proxy user possible'],
        'References' => ['References', 'Databases,Tables', 'To have references on tables'],
        'Reload' => ['Reload', 'Server Admin', 'To reload or refresh tables, logs and privileges'],
        'Replication client' => ['Replication client', 'Server Admin', 'To ask where the slave or master servers are'],
        'Replication slave' => ['Replication slave', 'Server Admin', 'To read binary log events from the master'],
        'Select' => ['Select', 'Tables', 'To retrieve rows from table'],
        'Show databases' => ['Show databases', 'Server Admin', 'To see all databases with SHOW DATABASES'],
        'Show view' => ['Show view', 'Tables', 'To see views with SHOW CREATE VIEW'],
        'Shutdown' => ['Shutdown', 'Server Admin', 'To shut down the server'],
        'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE REPLICATION SOURCE, etc.'],
        'Trigger' => ['Trigger', 'Tables', 'To use triggers'],
        'Create tablespace' => ['Create tablespace', 'Server Admin', 'To create/alter/drop tablespaces'],
        'Update' => ['Update', 'Tables', 'To update existing rows'],
        'Usage' => ['Usage', 'Server Admin', 'No privileges - allow connect only'],
        'AUDIT_ABORT_EXEMPT' => ['AUDIT_ABORT_EXEMPT', 'Server Admin', ''],
        'FIREWALL_EXEMPT' => ['FIREWALL_EXEMPT', 'Server Admin', ''],
        'OPTIMIZE_LOCAL_TABLE' => ['OPTIMIZE_LOCAL_TABLE', 'Server Admin', ''],
        'ALLOW_NONEXISTENT_DEFINER' => ['ALLOW_NONEXISTENT_DEFINER', 'Server Admin', ''],
        'SET_ANY_DEFINER' => ['SET_ANY_DEFINER', 'Server Admin', ''],
        'SENSITIVE_VARIABLES_OBSERVER' => ['SENSITIVE_VARIABLES_OBSERVER', 'Server Admin', ''],
        'AUTHENTICATION_POLICY_ADMIN' => ['AUTHENTICATION_POLICY_ADMIN', 'Server Admin', ''],
        'GROUP_REPLICATION_STREAM' => ['GROUP_REPLICATION_STREAM', 'Server Admin', ''],
        'FLUSH_PRIVILEGES' => ['FLUSH_PRIVILEGES', 'Server Admin', ''],
        'XA_RECOVER_ADMIN' => ['XA_RECOVER_ADMIN', 'Server Admin', ''],
        'CONNECTION_ADMIN' => ['CONNECTION_ADMIN', 'Server Admin', ''],
        'CLONE_ADMIN' => ['CLONE_ADMIN', 'Server Admin', ''],
        'ENCRYPTION_KEY_ADMIN' => ['ENCRYPTION_KEY_ADMIN', 'Server Admin', ''],
        'INNODB_REDO_LOG_ARCHIVE' => ['INNODB_REDO_LOG_ARCHIVE', 'Server Admin', ''],
        'SESSION_VARIABLES_ADMIN' => ['SESSION_VARIABLES_ADMIN', 'Server Admin', ''],
        'APPLICATION_PASSWORD_ADMIN' => ['APPLICATION_PASSWORD_ADMIN', 'Server Admin', ''],
        'REPLICATION_SLAVE_ADMIN' => ['REPLICATION_SLAVE_ADMIN', 'Server Admin', ''],
        'BACKUP_ADMIN' => ['BACKUP_ADMIN', 'Server Admin', ''],
        'GROUP_REPLICATION_ADMIN' => ['GROUP_REPLICATION_ADMIN', 'Server Admin', ''],
        'SYSTEM_VARIABLES_ADMIN' => ['SYSTEM_VARIABLES_ADMIN', 'Server Admin', ''],
        'BINLOG_ADMIN' => ['BINLOG_ADMIN', 'Server Admin', ''],
        'PERSIST_RO_VARIABLES_ADMIN' => ['PERSIST_RO_VARIABLES_ADMIN', 'Server Admin', ''],
        'TRANSACTION_GTID_TAG' => ['TRANSACTION_GTID_TAG', 'Server Admin', ''],
        'PASSWORDLESS_USER_ADMIN' => ['PASSWORDLESS_USER_ADMIN', 'Server Admin', ''],
        'ROLE_ADMIN' => ['ROLE_ADMIN', 'Server Admin', ''],
        'INNODB_REDO_LOG_ENABLE' => ['INNODB_REDO_LOG_ENABLE', 'Server Admin', ''],
        'RESOURCE_GROUP_USER' => ['RESOURCE_GROUP_USER', 'Server Admin', ''],
        'BINLOG_ENCRYPTION_ADMIN' => ['BINLOG_ENCRYPTION_ADMIN', 'Server Admin', ''],
        'SERVICE_CONNECTION_ADMIN' => ['SERVICE_CONNECTION_ADMIN', 'Server Admin', ''],
        'SHOW_ROUTINE' => ['SHOW_ROUTINE', 'Server Admin', ''],
        'RESOURCE_GROUP_ADMIN' => ['RESOURCE_GROUP_ADMIN', 'Server Admin', ''],
        'SYSTEM_USER' => ['SYSTEM_USER', 'Server Admin', ''],
        'TABLE_ENCRYPTION_ADMIN' => ['TABLE_ENCRYPTION_ADMIN', 'Server Admin', ''],
        'TELEMETRY_LOG_ADMIN' => ['TELEMETRY_LOG_ADMIN', 'Server Admin', ''],
        'FLUSH_STATUS' => ['FLUSH_STATUS', 'Server Admin', ''],
        'REPLICATION_APPLIER' => ['REPLICATION_APPLIER', 'Server Admin', ''],
        'FLUSH_OPTIMIZER_COSTS' => ['FLUSH_OPTIMIZER_COSTS', 'Server Admin', ''],
        'AUDIT_ADMIN' => ['AUDIT_ADMIN', 'Server Admin', ''],
        'FLUSH_USER_RESOURCES' => ['FLUSH_USER_RESOURCES', 'Server Admin', ''],
        'FLUSH_TABLES' => ['FLUSH_TABLES', 'Server Admin', ''],
    ];

    /**
     * @var array<string, array<string, array{string, string, string}>> Release changes to the common definitions
     */
    private const CHANGES = [
        'mysql-5.6.51' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
        ],
        'mysql-5.7.44' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
        ],
        'mysql-8.0.44' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
            'SET_USER_ID' => ['SET_USER_ID', 'Server Admin', ''],
        ],
        'mysql-8.1.0' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
            'SET_USER_ID' => ['SET_USER_ID', 'Server Admin', ''],
        ],
        'mysql-8.2.0' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
            'SET_USER_ID' => ['SET_USER_ID', 'Server Admin', ''],
        ],
        'mysql-8.3.0' => [
            'Super' => ['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'],
            'SET_USER_ID' => ['SET_USER_ID', 'Server Admin', ''],
        ],
    ];

    /**
     * @var array<string, list<string>> Available definitions in server order, for every supported release
     */
    private const RELEASES = [
        'mysql-5.6.51' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage'],
        'mysql-5.7.44' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage'],
        'mysql-8.0.44' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'XA_RECOVER_ADMIN', 'TELEMETRY_LOG_ADMIN', 'TABLE_ENCRYPTION_ADMIN', 'SHOW_ROUTINE', 'SET_USER_ID', 'SERVICE_CONNECTION_ADMIN', 'SENSITIVE_VARIABLES_OBSERVER', 'GROUP_REPLICATION_STREAM', 'REPLICATION_SLAVE_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'SESSION_VARIABLES_ADMIN', 'CLONE_ADMIN', 'CONNECTION_ADMIN', 'SYSTEM_VARIABLES_ADMIN', 'BACKUP_ADMIN', 'AUTHENTICATION_POLICY_ADMIN', 'REPLICATION_APPLIER', 'RESOURCE_GROUP_ADMIN', 'SYSTEM_USER', 'FIREWALL_EXEMPT', 'AUDIT_ABORT_EXEMPT', 'BINLOG_ENCRYPTION_ADMIN', 'INNODB_REDO_LOG_ARCHIVE', 'ENCRYPTION_KEY_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'BINLOG_ADMIN', 'FLUSH_OPTIMIZER_COSTS', 'FLUSH_STATUS', 'FLUSH_TABLES', 'AUDIT_ADMIN', 'FLUSH_USER_RESOURCES', 'GROUP_REPLICATION_ADMIN', 'INNODB_REDO_LOG_ENABLE', 'PASSWORDLESS_USER_ADMIN', 'ROLE_ADMIN', 'RESOURCE_GROUP_USER'],
        'mysql-8.1.0' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'TELEMETRY_LOG_ADMIN', 'SHOW_ROUTINE', 'RESOURCE_GROUP_USER', 'REPLICATION_APPLIER', 'PASSWORDLESS_USER_ADMIN', 'INNODB_REDO_LOG_ENABLE', 'XA_RECOVER_ADMIN', 'GROUP_REPLICATION_STREAM', 'GROUP_REPLICATION_ADMIN', 'SENSITIVE_VARIABLES_OBSERVER', 'FLUSH_USER_RESOURCES', 'FLUSH_TABLES', 'FLUSH_STATUS', 'PERSIST_RO_VARIABLES_ADMIN', 'AUTHENTICATION_POLICY_ADMIN', 'BINLOG_ADMIN', 'ROLE_ADMIN', 'BACKUP_ADMIN', 'CONNECTION_ADMIN', 'BINLOG_ENCRYPTION_ADMIN', 'RESOURCE_GROUP_ADMIN', 'INNODB_REDO_LOG_ARCHIVE', 'AUDIT_ABORT_EXEMPT', 'FLUSH_OPTIMIZER_COSTS', 'CLONE_ADMIN', 'SET_USER_ID', 'SESSION_VARIABLES_ADMIN', 'REPLICATION_SLAVE_ADMIN', 'SYSTEM_VARIABLES_ADMIN', 'FIREWALL_EXEMPT', 'SYSTEM_USER', 'APPLICATION_PASSWORD_ADMIN', 'TABLE_ENCRYPTION_ADMIN', 'SERVICE_CONNECTION_ADMIN', 'AUDIT_ADMIN', 'ENCRYPTION_KEY_ADMIN'],
        'mysql-8.2.0' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'FIREWALL_EXEMPT', 'AUDIT_ABORT_EXEMPT', 'RESOURCE_GROUP_USER', 'INNODB_REDO_LOG_ARCHIVE', 'SET_USER_ID', 'FLUSH_OPTIMIZER_COSTS', 'BACKUP_ADMIN', 'CONNECTION_ADMIN', 'ROLE_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'XA_RECOVER_ADMIN', 'GROUP_REPLICATION_STREAM', 'REPLICATION_SLAVE_ADMIN', 'GROUP_REPLICATION_ADMIN', 'BINLOG_ADMIN', 'BINLOG_ENCRYPTION_ADMIN', 'TABLE_ENCRYPTION_ADMIN', 'SYSTEM_USER', 'RESOURCE_GROUP_ADMIN', 'TELEMETRY_LOG_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'REPLICATION_APPLIER', 'SHOW_ROUTINE', 'SET_ANY_DEFINER', 'INNODB_REDO_LOG_ENABLE', 'FLUSH_STATUS', 'FLUSH_USER_RESOURCES', 'CLONE_ADMIN', 'SENSITIVE_VARIABLES_OBSERVER', 'AUDIT_ADMIN', 'FLUSH_TABLES', 'SERVICE_CONNECTION_ADMIN', 'AUTHENTICATION_POLICY_ADMIN', 'SESSION_VARIABLES_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'ALLOW_NONEXISTENT_DEFINER', 'SYSTEM_VARIABLES_ADMIN', 'PASSWORDLESS_USER_ADMIN'],
        'mysql-8.3.0' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'FIREWALL_EXEMPT', 'AUDIT_ABORT_EXEMPT', 'TRANSACTION_GTID_TAG', 'RESOURCE_GROUP_USER', 'INNODB_REDO_LOG_ARCHIVE', 'SET_USER_ID', 'FLUSH_OPTIMIZER_COSTS', 'BACKUP_ADMIN', 'CONNECTION_ADMIN', 'ROLE_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'XA_RECOVER_ADMIN', 'GROUP_REPLICATION_STREAM', 'REPLICATION_SLAVE_ADMIN', 'GROUP_REPLICATION_ADMIN', 'BINLOG_ADMIN', 'BINLOG_ENCRYPTION_ADMIN', 'TABLE_ENCRYPTION_ADMIN', 'SYSTEM_USER', 'RESOURCE_GROUP_ADMIN', 'TELEMETRY_LOG_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'REPLICATION_APPLIER', 'SHOW_ROUTINE', 'SET_ANY_DEFINER', 'INNODB_REDO_LOG_ENABLE', 'FLUSH_STATUS', 'FLUSH_USER_RESOURCES', 'CLONE_ADMIN', 'SENSITIVE_VARIABLES_OBSERVER', 'AUDIT_ADMIN', 'FLUSH_TABLES', 'SERVICE_CONNECTION_ADMIN', 'AUTHENTICATION_POLICY_ADMIN', 'SESSION_VARIABLES_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'ALLOW_NONEXISTENT_DEFINER', 'SYSTEM_VARIABLES_ADMIN', 'PASSWORDLESS_USER_ADMIN'],
        'mysql-8.4.7' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'AUDIT_ABORT_EXEMPT', 'FIREWALL_EXEMPT', 'OPTIMIZE_LOCAL_TABLE', 'ALLOW_NONEXISTENT_DEFINER', 'SET_ANY_DEFINER', 'SENSITIVE_VARIABLES_OBSERVER', 'AUTHENTICATION_POLICY_ADMIN', 'GROUP_REPLICATION_STREAM', 'FLUSH_PRIVILEGES', 'XA_RECOVER_ADMIN', 'CONNECTION_ADMIN', 'CLONE_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'INNODB_REDO_LOG_ARCHIVE', 'SESSION_VARIABLES_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'REPLICATION_SLAVE_ADMIN', 'BACKUP_ADMIN', 'GROUP_REPLICATION_ADMIN', 'SYSTEM_VARIABLES_ADMIN', 'BINLOG_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'TRANSACTION_GTID_TAG', 'PASSWORDLESS_USER_ADMIN', 'ROLE_ADMIN', 'INNODB_REDO_LOG_ENABLE', 'RESOURCE_GROUP_USER', 'BINLOG_ENCRYPTION_ADMIN', 'SERVICE_CONNECTION_ADMIN', 'SHOW_ROUTINE', 'RESOURCE_GROUP_ADMIN', 'SYSTEM_USER', 'TABLE_ENCRYPTION_ADMIN', 'TELEMETRY_LOG_ADMIN', 'FLUSH_STATUS', 'REPLICATION_APPLIER', 'FLUSH_OPTIMIZER_COSTS', 'AUDIT_ADMIN', 'FLUSH_USER_RESOURCES', 'FLUSH_TABLES'],
        'mysql-9.0.1' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'AUDIT_ABORT_EXEMPT', 'FIREWALL_EXEMPT', 'OPTIMIZE_LOCAL_TABLE', 'ALLOW_NONEXISTENT_DEFINER', 'SET_ANY_DEFINER', 'SENSITIVE_VARIABLES_OBSERVER', 'AUTHENTICATION_POLICY_ADMIN', 'GROUP_REPLICATION_STREAM', 'FLUSH_PRIVILEGES', 'XA_RECOVER_ADMIN', 'CONNECTION_ADMIN', 'CLONE_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'INNODB_REDO_LOG_ARCHIVE', 'SESSION_VARIABLES_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'REPLICATION_SLAVE_ADMIN', 'BACKUP_ADMIN', 'GROUP_REPLICATION_ADMIN', 'SYSTEM_VARIABLES_ADMIN', 'BINLOG_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'TRANSACTION_GTID_TAG', 'PASSWORDLESS_USER_ADMIN', 'ROLE_ADMIN', 'INNODB_REDO_LOG_ENABLE', 'RESOURCE_GROUP_USER', 'BINLOG_ENCRYPTION_ADMIN', 'SERVICE_CONNECTION_ADMIN', 'SHOW_ROUTINE', 'RESOURCE_GROUP_ADMIN', 'SYSTEM_USER', 'TABLE_ENCRYPTION_ADMIN', 'TELEMETRY_LOG_ADMIN', 'FLUSH_STATUS', 'REPLICATION_APPLIER', 'FLUSH_OPTIMIZER_COSTS', 'AUDIT_ADMIN', 'FLUSH_USER_RESOURCES', 'FLUSH_TABLES'],
        'mysql-9.1.0' => ['Alter', 'Alter routine', 'Create', 'Create routine', 'Create role', 'Create temporary tables', 'Create view', 'Create user', 'Delete', 'Drop', 'Drop role', 'Event', 'Execute', 'File', 'Grant option', 'Index', 'Insert', 'Lock tables', 'Process', 'Proxy', 'References', 'Reload', 'Replication client', 'Replication slave', 'Select', 'Show databases', 'Show view', 'Shutdown', 'Super', 'Trigger', 'Create tablespace', 'Update', 'Usage', 'AUDIT_ABORT_EXEMPT', 'FIREWALL_EXEMPT', 'OPTIMIZE_LOCAL_TABLE', 'ALLOW_NONEXISTENT_DEFINER', 'SET_ANY_DEFINER', 'SENSITIVE_VARIABLES_OBSERVER', 'AUTHENTICATION_POLICY_ADMIN', 'GROUP_REPLICATION_STREAM', 'FLUSH_PRIVILEGES', 'XA_RECOVER_ADMIN', 'CONNECTION_ADMIN', 'CLONE_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'INNODB_REDO_LOG_ARCHIVE', 'SESSION_VARIABLES_ADMIN', 'APPLICATION_PASSWORD_ADMIN', 'REPLICATION_SLAVE_ADMIN', 'BACKUP_ADMIN', 'GROUP_REPLICATION_ADMIN', 'SYSTEM_VARIABLES_ADMIN', 'BINLOG_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN', 'TRANSACTION_GTID_TAG', 'PASSWORDLESS_USER_ADMIN', 'ROLE_ADMIN', 'INNODB_REDO_LOG_ENABLE', 'RESOURCE_GROUP_USER', 'BINLOG_ENCRYPTION_ADMIN', 'SERVICE_CONNECTION_ADMIN', 'SHOW_ROUTINE', 'RESOURCE_GROUP_ADMIN', 'SYSTEM_USER', 'TABLE_ENCRYPTION_ADMIN', 'TELEMETRY_LOG_ADMIN', 'FLUSH_STATUS', 'REPLICATION_APPLIER', 'FLUSH_OPTIMIZER_COSTS', 'AUDIT_ADMIN', 'FLUSH_USER_RESOURCES', 'FLUSH_TABLES'],
    ];

    /**
     * @throws RuntimeException When the release or one of its declarations is unavailable
     * @return list<array{string, string, string}>
     */
    public static function of(GrammarRelease $release): array
    {
        $definitions = (self::CHANGES[$release->value] ?? []) + self::COMMON;
        $names = self::RELEASES[$release->value] ?? throw new RuntimeException('A supported MySQL release is required.');
        $rows = [];
        foreach ($names as $name) {
            $row = $definitions[$name] ?? throw new RuntimeException('Missing built-in declaration: ' . $name);
            $rows[] = $row;
        }
        return $rows;
    }
}
