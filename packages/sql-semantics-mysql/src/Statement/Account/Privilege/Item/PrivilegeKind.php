<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

/**
 * The static privileges, by the words GRANT and REVOKE write them with.
 *
 * Mirrors the ACL bits of sql/auth/auth_acls.h. The levels a privilege can be
 * granted at follow the masks there: TABLE_ACLS for a table, PROC_ACLS for a
 * routine, DB_ACLS for a database; every privilege can be granted
 * globally. USAGE is no privilege and fits every level.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html,
 * https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privileges.
 *
 * @visibility public
 * @example Asking where a privilege can be granted
 *     [\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Reload->database(), \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Execute->routine()] // => [false, true]
 */
enum PrivilegeKind: string
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
     * Answers the keywords of the privilege.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return explode(' ', $this->value);
    }

    /**
     * Tells whether the privilege is granted on columns: SELECT, INSERT, UPDATE and REFERENCES (COL_ACLS).
     */
    public function columns(): bool
    {
        return in_array($this, [self::Select, self::Insert, self::Update, self::References], true);
    }

    /**
     * Tells whether the privilege can be granted on a table (TABLE_ACLS).
     */
    public function table(): bool
    {
        return $this->columns() || in_array($this, [self::Delete, self::Usage, self::Create, self::Drop, self::GrantOption, self::Index, self::Alter, self::CreateView, self::ShowView, self::Trigger], true);
    }

    /**
     * Tells whether the privilege can be granted on a stored routine (PROC_ACLS).
     */
    public function routine(): bool
    {
        return in_array($this, [self::Usage, self::AlterRoutine, self::Execute, self::GrantOption], true);
    }

    /**
     * Tells whether the privilege can be granted on a database (DB_ACLS).
     */
    public function database(): bool
    {
        return $this->table() || in_array($this, [self::CreateTemporaryTables, self::LockTables, self::Execute, self::CreateRoutine, self::AlterRoutine, self::Event], true);
    }
}
