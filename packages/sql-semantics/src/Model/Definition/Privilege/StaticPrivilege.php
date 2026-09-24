<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

/**
 * The fixed privilege keywords MySQL grants and revokes; role privileges exist only in MySQL 8.
 * @visibility public
 * @example Inspecting a privilege keyword
 *     \SqlSemantics\Model\Definition\Privilege\StaticPrivilege::CreateTemporaryTables->value // => 'CREATE TEMPORARY TABLES'
 * @example Reading the levels a privilege accepts
 *     \SqlSemantics\Model\Definition\Privilege\StaticPrivilege::Execute->levels() // => [\SqlSemantics\Model\Definition\Privilege\PrivilegeLevel::Global, \SqlSemantics\Model\Definition\Privilege\PrivilegeLevel::Database, \SqlSemantics\Model\Definition\Privilege\PrivilegeLevel::Routine]
 */
enum StaticPrivilege: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case References = 'REFERENCES';
    case Delete = 'DELETE';
    case Usage = 'USAGE';
    case Index = 'INDEX';
    case Alter = 'ALTER';
    case Create = 'CREATE';
    case Drop = 'DROP';
    case Execute = 'EXECUTE';
    case Reload = 'RELOAD';
    case Shutdown = 'SHUTDOWN';
    case Process = 'PROCESS';
    case File = 'FILE';
    case GrantOption = 'GRANT OPTION';
    case ShowDatabases = 'SHOW DATABASES';
    case Super = 'SUPER';
    case CreateTemporaryTables = 'CREATE TEMPORARY TABLES';
    case LockTables = 'LOCK TABLES';
    case ReplicationSlave = 'REPLICATION SLAVE';
    case ReplicationClient = 'REPLICATION CLIENT';
    case CreateView = 'CREATE VIEW';
    case ShowView = 'SHOW VIEW';
    case CreateRoutine = 'CREATE ROUTINE';
    case AlterRoutine = 'ALTER ROUTINE';
    case CreateUser = 'CREATE USER';
    case Event = 'EVENT';
    case Trigger = 'TRIGGER';
    case CreateTablespace = 'CREATE TABLESPACE';
    case CreateRole = 'CREATE ROLE';
    case DropRole = 'DROP ROLE';

    /**
     * The levels at which MySQL accepts this privilege; USAGE and GRANT OPTION apply everywhere.
     * @return non-empty-list<PrivilegeLevel>
     */
    public function levels(): array
    {
        return match ($this) {
            self::Usage, self::GrantOption => [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table, PrivilegeLevel::Routine],
            self::Select, self::Insert, self::Update, self::References, self::Delete, self::Index, self::Alter, self::Create, self::Drop, self::CreateView, self::ShowView, self::Trigger => [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table],
            self::Execute, self::AlterRoutine => [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Routine],
            self::CreateTemporaryTables, self::LockTables, self::CreateRoutine, self::Event => [PrivilegeLevel::Global, PrivilegeLevel::Database],
            self::Reload, self::Shutdown, self::Process, self::File, self::ShowDatabases, self::Super, self::ReplicationSlave, self::ReplicationClient, self::CreateUser, self::CreateTablespace, self::CreateRole, self::DropRole => [PrivilegeLevel::Global],
        };
    }
}
