<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\DropOwned;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\ReassignOwned;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Statement\Statement;

/**
 * Lowers ALTER DEFAULT PRIVILEGES, DROP OWNED and REASSIGN OWNED.
 *
 * Rule: PG-DEFAULT-PRIVILEGES-LOWER-001. Scope: `AlterDefaultPrivilegesStmt`,
 * `DefACLOptionList`, `DefACLOption`, `DefACLAction`,
 * `defacl_privilege_target`, `DropOwnedStmt`, `ReassignOwnedStmt`.
 * Constructors: `AlterDefaultPrivileges`, `InSchemas`, `ForRoles`,
 * `DefaultGrant`, `DefaultRevoke`, `DropOwned`, `ReassignOwned`; the
 * privileges and grantees are lowered by PG-PRIVILEGE-LOWER-001.
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html, https://www.postgresql.org/docs/17/sql-drop-owned.html,
 * https://www.postgresql.org/docs/17/sql-reassign-owned.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class DefaultPrivilegeRule
{
    /**
     * The kind of object each `defacl_privilege_target` production names.
     */
    private const OBJECTS = [
        'defacl_privilege_target: TABLES' => DefaultObjectKind::Tables,
        'defacl_privilege_target: FUNCTIONS' => DefaultObjectKind::Functions,
        'defacl_privilege_target: ROUTINES' => DefaultObjectKind::Routines,
        'defacl_privilege_target: SEQUENCES' => DefaultObjectKind::Sequences,
        'defacl_privilege_target: TYPES_P' => DefaultObjectKind::Types,
        'defacl_privilege_target: SCHEMAS' => DefaultObjectKind::Schemas,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `AlterDefaultPrivilegesStmt`, `DropOwnedStmt` or `ReassignOwnedStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $roles = $this->lowering->roles;

        return match ($form->signature) {
            'AlterDefaultPrivilegesStmt: ALTER DEFAULT PRIVILEGES DefACLOptionList DefACLAction' => new AlterDefaultPrivileges($this->scopes($form->node(3)), $this->action($form->node(4))),
            'DropOwnedStmt: DROP OWNED BY role_list opt_drop_behavior' => new DropOwned($roles->roles($form->node(3)), $this->lowering->flags->dropBehavior($form->node(4))),
            'ReassignOwnedStmt: REASSIGN OWNED BY role_list TO RoleSpec' => new ReassignOwned($roles->roles($form->node(3)), $roles->role($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `DefACLOptionList`: the limiting clauses in order.
     *
     * @return list<DefaultScope>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function scopes(Node $list): array
    {
        $scopes = [];
        foreach ($this->lowering->items($list, 'DefACLOptionList: DefACLOptionList DefACLOption', 'DefACLOptionList:') as $item) {
            $form = $this->lowering->productions->form($item);
            $scopes[] = match ($form->signature) {
                'DefACLOption: IN_P SCHEMA name_list' => new InSchemas($this->lowering->names->names($form->node(2))),
                'DefACLOption: FOR ROLE role_list' => new ForRoles(RoleWord::Role, $this->lowering->roles->roles($form->node(2))),
                'DefACLOption: FOR USER role_list' => new ForRoles(RoleWord::User, $this->lowering->roles->roles($form->node(2))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $scopes;
    }

    /**
     * Lowers `DefACLAction`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function action(Node $action): DefaultGrant|DefaultRevoke
    {
        $form = $this->lowering->productions->form($action);
        $parts = new PrivilegeRule($this->lowering);
        $flags = $this->lowering->flags;

        return match ($form->signature) {
            'DefACLAction: GRANT privileges ON defacl_privilege_target TO grantee_list opt_grant_grant_option' => new DefaultGrant($parts->privileges($form->node(1)), $this->objects($form->node(3)), $parts->grantees($form->node(5)), $parts->grantOption($form->node(6))),
            'DefACLAction: REVOKE privileges ON defacl_privilege_target FROM grantee_list opt_drop_behavior' => new DefaultRevoke($parts->privileges($form->node(1)), $this->objects($form->node(3)), $parts->grantees($form->node(5)), false, $flags->dropBehavior($form->node(6))),
            'DefACLAction: REVOKE GRANT OPTION FOR privileges ON defacl_privilege_target FROM grantee_list opt_drop_behavior' => new DefaultRevoke($parts->privileges($form->node(4)), $this->objects($form->node(6)), $parts->grantees($form->node(8)), true, $flags->dropBehavior($form->node(9))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `defacl_privilege_target`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function objects(Node $target): DefaultObjectKind
    {
        $form = $this->lowering->productions->form($target);

        return self::OBJECTS[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
