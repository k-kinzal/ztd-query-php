<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterGroupMembers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRoleSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\CreateRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\DropRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleSystemId;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleValidity;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the role commands.
 *
 * Rule: PG-ROLE-LOWER-001. Scope: `CreateRoleStmt`, `CreateUserStmt`,
 * `CreateGroupStmt`, `OptRoleList`, `CreateOptRoleElem`, `AlterRoleStmt`,
 * `AlterOptRoleList`, `AlterOptRoleElem`, `AlterRoleSetStmt`,
 * `opt_in_database`, `AlterGroupStmt`, `DropRoleStmt`. Constructors:
 * `CreateRole`, `AlterRole`, `AlterRoleSetting`, `AlterGroupMembers`,
 * `DropRole` and the `RoleOption` classes. The optional WITH before the
 * options (LeafNoise) and ENCRYPTED before PASSWORD (AccessNoise) are
 * noise. UNENCRYPTED PASSWORD and an unknown option word, which the server
 * rejects in the grammar action, are lowered and reported by derivation.
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html, https://www.postgresql.org/docs/17/sql-alterrole.html,
 * https://www.postgresql.org/docs/17/sql-altergroup.html, https://www.postgresql.org/docs/17/sql-droprole.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class RoleRule
{
    /**
     * The statement word and IF EXISTS of each `DropRoleStmt` production, with the position of the role list.
     */
    private const DROPS = [
        'DropRoleStmt: DROP ROLE role_list' => [RoleWord::Role, 2, false],
        'DropRoleStmt: DROP ROLE IF_P EXISTS role_list' => [RoleWord::Role, 4, true],
        'DropRoleStmt: DROP USER role_list' => [RoleWord::User, 2, false],
        'DropRoleStmt: DROP USER IF_P EXISTS role_list' => [RoleWord::User, 4, true],
        'DropRoleStmt: DROP GROUP_P role_list' => [RoleWord::Group, 2, false],
        'DropRoleStmt: DROP GROUP_P IF_P EXISTS role_list' => [RoleWord::Group, 4, true],
    ];

    /**
     * The meaning of each role list option.
     */
    private const MEMBERS = [
        'AlterOptRoleElem: USER role_list' => [RoleMembersKind::User, 1],
        'CreateOptRoleElem: ADMIN role_list' => [RoleMembersKind::Admin, 1],
        'CreateOptRoleElem: ROLE role_list' => [RoleMembersKind::Role, 1],
        'CreateOptRoleElem: IN_P ROLE role_list' => [RoleMembersKind::InRole, 2],
        'CreateOptRoleElem: IN_P GROUP_P role_list' => [RoleMembersKind::InGroup, 2],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a role command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $roles = $this->lowering->roles;
        if (isset(self::DROPS[$form->signature])) {
            [$word, $position, $ifExists] = self::DROPS[$form->signature];

            return new DropRole($word, $roles->roles($form->node($position)), $ifExists);
        }

        return match ($form->signature) {
            'CreateRoleStmt: CREATE ROLE RoleId opt_with OptRoleList' => new CreateRole(RoleWord::Role, $roles->name($form->node(2)), $this->options($form->node(4))),
            'CreateUserStmt: CREATE USER RoleId opt_with OptRoleList' => new CreateRole(RoleWord::User, $roles->name($form->node(2)), $this->options($form->node(4))),
            'CreateGroupStmt: CREATE GROUP_P RoleId opt_with OptRoleList' => new CreateRole(RoleWord::Group, $roles->name($form->node(2)), $this->options($form->node(4))),
            'AlterRoleStmt: ALTER ROLE RoleSpec opt_with AlterOptRoleList' => new AlterRole(RoleWord::Role, $roles->role($form->node(2)), $this->options($form->node(4))),
            'AlterRoleStmt: ALTER USER RoleSpec opt_with AlterOptRoleList' => new AlterRole(RoleWord::User, $roles->role($form->node(2)), $this->options($form->node(4))),
            'AlterRoleSetStmt: ALTER ROLE RoleSpec opt_in_database SetResetClause' => new AlterRoleSetting(RoleWord::Role, $roles->role($form->node(2)), $this->database($form->node(3)), $this->lowering->utilities->setReset($form->node(4))),
            'AlterRoleSetStmt: ALTER ROLE ALL opt_in_database SetResetClause' => new AlterRoleSetting(RoleWord::Role, null, $this->database($form->node(3)), $this->lowering->utilities->setReset($form->node(4))),
            'AlterRoleSetStmt: ALTER USER RoleSpec opt_in_database SetResetClause' => new AlterRoleSetting(RoleWord::User, $roles->role($form->node(2)), $this->database($form->node(3)), $this->lowering->utilities->setReset($form->node(4))),
            'AlterRoleSetStmt: ALTER USER ALL opt_in_database SetResetClause' => new AlterRoleSetting(RoleWord::User, null, $this->database($form->node(3)), $this->lowering->utilities->setReset($form->node(4))),
            'AlterGroupStmt: ALTER GROUP_P RoleSpec add_drop USER role_list' => new AlterGroupMembers($roles->role($form->node(2)), $this->lowering->flags->addOrDrop($form->node(3)), $roles->roles($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptRoleList` or `AlterOptRoleList`: the options in order.
     *
     * @return list<RoleOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $spine = $list->name === 'OptRoleList'
            ? ['OptRoleList: OptRoleList CreateOptRoleElem', 'OptRoleList:']
            : ['AlterOptRoleList: AlterOptRoleList AlterOptRoleElem', 'AlterOptRoleList:'];
        $options = [];
        foreach ($this->lowering->items($list, ...$spine) as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers `CreateOptRoleElem` or `AlterOptRoleElem`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $item): RoleOption
    {
        $form = $this->lowering->productions->form($item);
        $literals = $this->lowering->literals;
        if (isset(self::MEMBERS[$form->signature])) {
            [$kind, $position] = self::MEMBERS[$form->signature];

            return new RoleMembers($kind, $this->lowering->roles->roles($form->node($position)));
        }

        return match ($form->signature) {
            'CreateOptRoleElem: AlterOptRoleElem' => $this->option($form->node(0)),
            'CreateOptRoleElem: SYSID Iconst' => new RoleSystemId($literals->integer($form->node(1))),
            'AlterOptRoleElem: PASSWORD Sconst' => new RolePassword($literals->string($form->node(1))),
            'AlterOptRoleElem: PASSWORD NULL_P' => new RolePassword(null),
            'AlterOptRoleElem: ENCRYPTED PASSWORD Sconst' => new RolePassword($literals->string($form->node(2))),
            'AlterOptRoleElem: UNENCRYPTED PASSWORD Sconst' => new RolePassword($literals->string($form->node(2)), true),
            'AlterOptRoleElem: INHERIT' => new RoleInherit(),
            'AlterOptRoleElem: CONNECTION LIMIT SignedIconst' => new RoleConnectionLimit($literals->signed($form->node(2))),
            'AlterOptRoleElem: VALID UNTIL Sconst' => new RoleValidity($literals->string($form->node(2))),
            'AlterOptRoleElem: IDENT' => new RoleAttribute($this->lowering->names->token($form->token(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_in_database`; no database is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function database(Node $database): ?Name
    {
        $form = $this->lowering->productions->form($database);

        return match ($form->signature) {
            'opt_in_database: IN_P DATABASE name' => $this->lowering->names->name($form->node(2)),
            'opt_in_database:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
