<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers the privilege lists, column lists and privilege levels of GRANT and REVOKE.
 *
 * Rule: MYSQL-ACCOUNT-PRIVILEGE-001. Scope: role_or_privilege_list,
 * role_or_privilege (8.0+), grant_privileges, object_privilege_list,
 * object_privilege (5.x), opt_privileges, opt_column_list, column_list,
 * column_list_id, grant_ident, opt_acl_type. A static privilege maps to its
 * PrivilegeKind by the words written; a name alone is a role in a role list
 * and a dynamic privilege in a privilege list, as the server reads
 * PT_role_or_dynamic_privilege; a name with a column list is a dynamic
 * privilege (PT_dynamic_privilege); a name with a host is a role
 * (PT_role_at_host). ALL [PRIVILEGES] is AllPrivileges. The level is `*.*`,
 * `*`, `db.*` or an object name; an absent or TABLE kind is a table.
 * Constructs: StaticPrivilege, DynamicPrivilege, GrantedRole, AllPrivileges,
 * GlobalLevel, CurrentDatabaseLevel, DatabaseLevel, ObjectLevel.
 * Terminates: lists are flattened iteratively; every other child is a strict
 * subtree. Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrivilegeRule
{
    /**
     * The static privilege productions of every release, by the privilege they write.
     */
    private const STATIC = [
        'object_privilege: ALTER' => PrivilegeKind::Alter,
        'object_privilege: ALTER ROUTINE_SYM' => PrivilegeKind::AlterRoutine,
        'object_privilege: CREATE' => PrivilegeKind::Create,
        'object_privilege: CREATE ROUTINE_SYM' => PrivilegeKind::CreateRoutine,
        'object_privilege: CREATE TABLESPACE' => PrivilegeKind::CreateTablespace,
        'object_privilege: CREATE TABLESPACE_SYM' => PrivilegeKind::CreateTablespace,
        'object_privilege: CREATE TEMPORARY TABLES' => PrivilegeKind::CreateTemporaryTables,
        'object_privilege: CREATE USER' => PrivilegeKind::CreateUser,
        'object_privilege: CREATE VIEW_SYM' => PrivilegeKind::CreateView,
        'object_privilege: DELETE_SYM' => PrivilegeKind::Delete,
        'object_privilege: DROP' => PrivilegeKind::Drop,
        'object_privilege: EVENT_SYM' => PrivilegeKind::Event,
        'object_privilege: EXECUTE_SYM' => PrivilegeKind::Execute,
        'object_privilege: FILE_SYM' => PrivilegeKind::File,
        'object_privilege: GRANT OPTION' => PrivilegeKind::GrantOption,
        'object_privilege: INDEX_SYM' => PrivilegeKind::Index,
        'object_privilege: INSERT opt_column_list' => PrivilegeKind::Insert,
        'object_privilege: LOCK_SYM TABLES' => PrivilegeKind::LockTables,
        'object_privilege: PROCESS' => PrivilegeKind::Process,
        'object_privilege: REFERENCES opt_column_list' => PrivilegeKind::References,
        'object_privilege: RELOAD' => PrivilegeKind::Reload,
        'object_privilege: REPLICATION CLIENT_SYM' => PrivilegeKind::ReplicationClient,
        'object_privilege: REPLICATION SLAVE' => PrivilegeKind::ReplicationSlave,
        'object_privilege: SELECT_SYM opt_column_list' => PrivilegeKind::Select,
        'object_privilege: SHOW DATABASES' => PrivilegeKind::ShowDatabases,
        'object_privilege: SHOW VIEW_SYM' => PrivilegeKind::ShowView,
        'object_privilege: SHUTDOWN' => PrivilegeKind::Shutdown,
        'object_privilege: SUPER_SYM' => PrivilegeKind::Super,
        'object_privilege: TRIGGER_SYM' => PrivilegeKind::Trigger,
        'object_privilege: UPDATE_SYM opt_column_list' => PrivilegeKind::Update,
        'object_privilege: USAGE' => PrivilegeKind::Usage,
        'role_or_privilege: ALTER' => PrivilegeKind::Alter,
        'role_or_privilege: ALTER ROUTINE_SYM' => PrivilegeKind::AlterRoutine,
        'role_or_privilege: CREATE' => PrivilegeKind::Create,
        'role_or_privilege: CREATE ROLE_SYM' => PrivilegeKind::CreateRole,
        'role_or_privilege: CREATE ROUTINE_SYM' => PrivilegeKind::CreateRoutine,
        'role_or_privilege: CREATE TABLESPACE_SYM' => PrivilegeKind::CreateTablespace,
        'role_or_privilege: CREATE TEMPORARY TABLES' => PrivilegeKind::CreateTemporaryTables,
        'role_or_privilege: CREATE USER' => PrivilegeKind::CreateUser,
        'role_or_privilege: CREATE VIEW_SYM' => PrivilegeKind::CreateView,
        'role_or_privilege: DELETE_SYM' => PrivilegeKind::Delete,
        'role_or_privilege: DROP' => PrivilegeKind::Drop,
        'role_or_privilege: DROP ROLE_SYM' => PrivilegeKind::DropRole,
        'role_or_privilege: EVENT_SYM' => PrivilegeKind::Event,
        'role_or_privilege: EXECUTE_SYM' => PrivilegeKind::Execute,
        'role_or_privilege: FILE_SYM' => PrivilegeKind::File,
        'role_or_privilege: GRANT OPTION' => PrivilegeKind::GrantOption,
        'role_or_privilege: INDEX_SYM' => PrivilegeKind::Index,
        'role_or_privilege: INSERT_SYM opt_column_list' => PrivilegeKind::Insert,
        'role_or_privilege: LOCK_SYM TABLES' => PrivilegeKind::LockTables,
        'role_or_privilege: PROCESS' => PrivilegeKind::Process,
        'role_or_privilege: REFERENCES opt_column_list' => PrivilegeKind::References,
        'role_or_privilege: RELOAD' => PrivilegeKind::Reload,
        'role_or_privilege: REPLICATION CLIENT_SYM' => PrivilegeKind::ReplicationClient,
        'role_or_privilege: REPLICATION SLAVE' => PrivilegeKind::ReplicationSlave,
        'role_or_privilege: SELECT_SYM opt_column_list' => PrivilegeKind::Select,
        'role_or_privilege: SHOW DATABASES' => PrivilegeKind::ShowDatabases,
        'role_or_privilege: SHOW VIEW_SYM' => PrivilegeKind::ShowView,
        'role_or_privilege: SHUTDOWN' => PrivilegeKind::Shutdown,
        'role_or_privilege: SUPER_SYM' => PrivilegeKind::Super,
        'role_or_privilege: TRIGGER_SYM' => PrivilegeKind::Trigger,
        'role_or_privilege: UPDATE_SYM opt_column_list' => PrivilegeKind::Update,
        'role_or_privilege: USAGE' => PrivilegeKind::Usage,
    ];

    /**
     * The list productions this rule flattens.
     */
    private const LISTS = [
        'role_or_privilege_list: role_or_privilege' => true, 'role_or_privilege_list: role_or_privilege_list , role_or_privilege' => true,
        'object_privilege_list: object_privilege' => true, 'object_privilege_list: object_privilege_list , object_privilege' => true,
        'column_list: column_list , column_list_id' => true, 'column_list: column_list_id' => true, 'column_list: ident' => true, 'column_list: column_list , ident' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the items of an 8.0 role_or_privilege_list, reading a name alone as a role or as a dynamic privilege.
     *
     * @return non-empty-list<Grantable>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list, bool $roles): array
    {
        $items = [];
        foreach ($this->spine($list) as $item) {
            $items[] = $this->item($item, $roles);
        }
        Check::invariant($items !== [], 'A privilege list holds at least one item.');

        return $items;
    }

    /**
     * Lowers one role_or_privilege or object_privilege.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function item(Node $item, bool $roles): Grantable
    {
        $form = $this->lowering->form($item);
        if ($item->name !== 'role_or_privilege' && $item->name !== 'object_privilege') {
            throw ImplementationGap::production($form);
        }
        $kind = self::STATIC[$form->signature] ?? null;
        if ($kind !== null) {
            return new StaticPrivilege($kind, $kind->columns() ? $this->columns($form->node(1)) : []);
        }
        $names = $this->lowering->names;

        return match ($form->signature) {
            'role_or_privilege: role_ident_or_text @ ident_or_text' => new GrantedRole($this->lowering->leaves->record(new AccountName($names->identifier($form->node(0)), $names->identifier($form->node(2))))),
            'role_or_privilege: role_ident_or_text opt_column_list' => $this->named($names->identifier($form->node(0)), $this->columns($form->node(1)), $roles),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Reads a name with its column list: a role in a role list when no columns are written, a dynamic privilege otherwise.
     *
     * @param list<Name> $columns
     */
    public function named(Name $name, array $columns, bool $roles): Grantable
    {
        return $roles && $columns === [] ? new GrantedRole($this->lowering->leaves->record(new AccountName($name))) : new DynamicPrivilege($name, $columns);
    }

    /**
     * Lowers a MySQL 5.x grant_privileges: ALL [PRIVILEGES] or a list of privileges.
     *
     * @return non-empty-list<Grantable>
     * @throws ImplementationGap When a production has no rule
     */
    public function privileges(Node $privileges): array
    {
        $form = $this->lowering->form($privileges);
        if ($form->signature === 'grant_privileges: ALL opt_privileges') {
            $this->words($form->node(1));

            return [new AllPrivileges()];
        }
        if ($form->signature !== 'grant_privileges: object_privilege_list') {
            throw ImplementationGap::production($form);
        }

        return $this->items($form->node(0), false);
    }

    /**
     * Confirms an opt_privileges production: PRIVILEGES is an optional word.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function words(Node $optional): void
    {
        $form = $this->lowering->form($optional);
        if ($form->signature !== 'opt_privileges:' && $form->signature !== 'opt_privileges: PRIVILEGES') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers an opt_column_list; an absent list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $optional): array
    {
        $form = $this->lowering->form($optional);
        if ($form->signature === 'opt_column_list:') {
            return [];
        }
        if ($form->signature !== 'opt_column_list: ( column_list )') {
            throw ImplementationGap::production($form);
        }
        $columns = [];
        foreach ($this->spine($form->node(1)) as $column) {
            $columns[] = $this->column($column);
        }

        return $columns;
    }

    /**
     * Lowers one column of a column list.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function column(Node $column): Name
    {
        if ($column->name !== 'column_list_id') {
            return $this->lowering->names->identifier($column);
        }
        $form = $this->lowering->form($column);
        if ($form->signature !== 'column_list_id: ident') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->identifier($form->node(0));
    }

    /**
     * Lowers a grant_ident.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function level(Node $level): PrivilegeLevel
    {
        $form = $this->lowering->form($level);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'grant_ident: * . *' => new GlobalLevel(),
            'grant_ident: *' => new CurrentDatabaseLevel(),
            'grant_ident: ident . *', 'grant_ident: schema . *' => new DatabaseLevel($names->identifier($form->node(0))),
            'grant_ident: ident' => new ObjectLevel(new QualifiedName($names->identifier($form->node(0)))),
            'grant_ident: schema . ident' => new ObjectLevel(new QualifiedName($names->identifier($form->node(2)), $names->identifier($form->node(0)))),
            'grant_ident: table_ident' => new ObjectLevel($names->qualified($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an 8.0 opt_acl_type: TABLE and an absent kind are a table.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function kind(Node $kind): ObjectKind
    {
        $form = $this->lowering->form($kind);

        return match ($form->signature) {
            'opt_acl_type:', 'opt_acl_type: TABLE_SYM' => ObjectKind::Table,
            'opt_acl_type: FUNCTION_SYM' => ObjectKind::Function,
            'opt_acl_type: PROCEDURE_SYM' => ObjectKind::Procedure,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Answers the items of a list production of this rule, in source order.
     *
     * @return list<Node>
     * @throws ImplementationGap When the production has no rule
     */
    public function spine(Node $list): array
    {
        $form = $this->lowering->form($list);
        if (!isset(self::LISTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }

        return (new Lists())->items($list);
    }
}
