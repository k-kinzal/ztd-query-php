<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the facts of the privilege list, level and object of GRANT and REVOKE.
 *
 * Rule: MYSQL-PRIVILEGE-CHECKS-001. A privilege fits a level as
 * sql/auth/auth_acls.h and the server's GRANT path (sql_parse.cc,
 * mysql_grant, mysql_table_grant, mysql_routine_grant) state: every static
 * privilege fits the global level; a database takes DB_ACLS
 * (ER_WRONG_USAGE otherwise), a table TABLE_ACLS and a routine PROC_ACLS
 * (ER_ILLEGAL_GRANT_FOR_TABLE otherwise); a column list fits a table only
 * (ER_ILLEGAL_GRANT_FOR_TABLE, or a syntax error after FUNCTION or
 * PROCEDURE); FUNCTION and PROCEDURE need a routine name
 * (ER_ILLEGAL_GRANT_FOR_TABLE); a dynamic privilege fits the global level
 * only (ER_ILLEGAL_PRIVILEGE_LEVEL, a warning under REVOKE IF EXISTS). ALL
 * fits every level. In a privilege list a role written with a host is a
 * RoleOrPrivilegeMismatch; in a role list a static privilege or a dynamic
 * privilege written with columns is one. The table of a level resolves by
 * CORE-TABLE-LOOKUP-001 and contributes the slots of its declaration; a
 * name a complete context does not declare is MissingTable for GRANT,
 * except that GRANT of CREATE or ALL without column privileges and every
 * REVOKE accept it as AbsentGrantTable. A column of a GRANT column privilege
 * that the completely declared table does not have is UnknownGrantColumn.
 * Precision: every slot of a declared table is a known type. Terminates: one
 * pass over the privileges and the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html,
 * https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrivilegeChecks
{
    /**
     * Derives the facts of a GRANT of privileges.
     *
     * @param list<Grantable> $privileges
     */
    public function grant(Derivation $derivation, array $privileges, ObjectKind $kind, PrivilegeLevel $level): void
    {
        $this->level($derivation, $privileges, $kind, $level, false);
        if (!$level instanceof ObjectLevel || $kind !== ObjectKind::Table) {
            return;
        }
        $columns = false;
        $creates = false;
        foreach ($privileges as $privilege) {
            $columns = $columns || ($privilege instanceof StaticPrivilege && $privilege->columns !== []);
            $creates = $creates || $privilege instanceof AllPrivileges || ($privilege instanceof StaticPrivilege && $privilege->kind === PrivilegeKind::Create);
        }
        $fact = $derivation->target($level, $this->target($derivation, $level, $creates && !$columns));
        if ($fact->table instanceof DeclaredTable && $fact->table->table->complete) {
            foreach ($privileges as $privilege) {
                foreach ($privilege instanceof StaticPrivilege ? $privilege->columns : [] as $column) {
                    if ($fact->table->table->matchingColumns($column->value, $derivation->context->columnNames) === []) {
                        $derivation->report(new UnknownGrantColumn($column, $level->name));
                    }
                }
            }
        }
    }

    /**
     * Derives the facts of a REVOKE of privileges.
     *
     * @param list<Grantable> $privileges
     */
    public function revoke(Derivation $derivation, array $privileges, ObjectKind $kind, PrivilegeLevel $level, bool $ifExists): void
    {
        $this->level($derivation, $privileges, $kind, $level, $ifExists);
        if ($level instanceof ObjectLevel && $kind === ObjectKind::Table) {
            $derivation->target($level, $this->target($derivation, $level, true));
        }
    }

    /**
     * Reports the privileges, column lists and object kind that do not fit the level.
     *
     * @param list<Grantable> $privileges
     * @param bool $lenient Whether a dynamic privilege outside the global level is only a warning (REVOKE IF EXISTS)
     */
    public function level(Derivation $derivation, array $privileges, ObjectKind $kind, PrivilegeLevel $level, bool $lenient): void
    {
        $object = $level instanceof ObjectLevel;
        if ($kind !== ObjectKind::Table && !$object) {
            $derivation->report(new MisplacedPrivilege((string) $kind->keyword(), $level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE'));
        }
        foreach ($privileges as $privilege) {
            if ($privilege instanceof GrantedRole) {
                $derivation->report(new RoleOrPrivilegeMismatch($this->describe($privilege), false));
            } elseif ($privilege instanceof DynamicPrivilege && !$level instanceof GlobalLevel && !$lenient) {
                $derivation->report(new MisplacedPrivilege($privilege->name->value, $level->describe(), 'ER_ILLEGAL_PRIVILEGE_LEVEL'));
            } elseif ($privilege instanceof StaticPrivilege) {
                $this->fit($derivation, $privilege, $kind, $level);
            }
        }
    }

    /**
     * Reports a static privilege or its column list that does not fit the level.
     */
    public function fit(Derivation $derivation, StaticPrivilege $privilege, ObjectKind $kind, PrivilegeLevel $level): void
    {
        $name = $privilege->kind->value;
        if ($privilege->columns !== [] && $kind !== ObjectKind::Table) {
            $derivation->report(new MisplacedPrivilege($name . ' with a column list', $level->describe(), 'ER_PARSE_ERROR'));
        } elseif ($privilege->columns !== [] && !$level instanceof ObjectLevel) {
            $derivation->report(new MisplacedPrivilege($name . ' with a column list', $level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE'));
        }
        if ($level instanceof GlobalLevel) {
            return;
        }
        if (!$level instanceof ObjectLevel) {
            if (!$privilege->kind->database()) {
                $derivation->report(new MisplacedPrivilege($name, $level->describe(), 'ER_WRONG_USAGE'));
            }

            return;
        }
        if (!($kind === ObjectKind::Table ? $privilege->kind->table() : $privilege->kind->routine())) {
            $derivation->report(new MisplacedPrivilege($name, $level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE'));
        }
    }

    /**
     * Reports the items of a role list of GRANT … TO or REVOKE … FROM that are no roles.
     *
     * @param list<Grantable> $roles
     */
    public function roles(Derivation $derivation, array $roles): void
    {
        foreach ($roles as $role) {
            if (!$role instanceof GrantedRole) {
                $derivation->report(new RoleOrPrivilegeMismatch($this->describe($role), true));
            }
        }
    }

    /**
     * Resolves the table of a level and answers its facts.
     *
     * @param bool $absentAllowed Whether a name a complete context does not declare is accepted
     */
    public function target(Derivation $derivation, ObjectLevel $level, bool $absentAllowed): RelationFact
    {
        $resolution = $derivation->table($level->name, $derivation->environment());
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }
        if ($resolution instanceof MissingTable && $absentAllowed) {
            return new RelationFact(new RowShape([]), new AbsentGrantTable($level->name));
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Describes an item of a privilege or role list for a person.
     */
    public function describe(Grantable $item): string
    {
        if ($item instanceof StaticPrivilege) {
            return $item->kind->value;
        }
        if ($item instanceof DynamicPrivilege) {
            return $item->name->value;
        }
        if ($item instanceof GrantedRole) {
            return $item->role->user->value . ($item->role->host === null ? '' : '@' . $item->role->host->value);
        }

        return 'ALL';
    }
}
