<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds GRANT, REVOKE, and ALTER DEFAULT PRIVILEGES without consulting catalog privileges.
 * @visibility SqlSemantics
 */
final class PrivilegeCommands
{
    /**
     * Returns null for statements outside the privilege family.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $identifiers = $context->tables->identifiers;
        return match ($source->name) {
            'GrantStmt', 'RevokeStmt' => self::objects($origin, $source, $context),
            'GrantRoleStmt' => new GrantRolesStatement($origin, Privileges::roles(self::child($source, 'privilege_list'), $identifiers), RoleSpecs::references(self::child($source, 'role_list'), $identifiers), Privileges::options(Tree::child($source, ['grant_role_opt_list']), $identifiers), self::grantor($source, $identifiers)),
            'RevokeRoleStmt' => self::memberships($origin, $source, $identifiers),
            'AlterDefaultPrivilegesStmt' => self::defaults($origin, $source, $identifiers),
            default => null,
        };
    }

    /**
     * GRANT and REVOKE share privileges, target, grantees, and grantor.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function objects(Origin $origin, Node $source, QueryContext $context): GrantPrivilegesStatement|RevokePrivilegesStatement
    {
        $identifiers = $context->tables->identifiers;
        $privileges = Privileges::read(self::child($source, 'privileges'), $identifiers);
        $target = PrivilegeTargets::read(self::child($source, 'privilege_target'), $origin, $context);
        self::check($target, $privileges, $source);
        $grantees = RoleSpecs::grantees(self::child($source, 'grantee_list'), $identifiers);
        $grantor = self::grantor($source, $identifiers);
        if ($source->name === 'GrantStmt') {
            return new GrantPrivilegesStatement($origin, $privileges, $target, $grantees, Tree::child($source, ['opt_grant_grant_option']) !== null, $grantor);
        }
        return new RevokePrivilegesStatement($origin, $privileges, $target, $grantees, ($source->tokens()[1]->name ?? '') === 'GRANT', $grantor, self::behavior($source));
    }

    /**
     * An option-only revocation names the option before OPTION FOR.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function memberships(Origin $origin, Node $source, Identifiers $identifiers): RevokeRolesStatement
    {
        $option = Tree::child($source, ['ColId']);
        return new RevokeRolesStatement(
            $origin,
            Privileges::roles(self::child($source, 'privilege_list'), $identifiers),
            RoleSpecs::references(self::child($source, 'role_list'), $identifiers),
            $option === null ? null : Privileges::attribute($option->tokens()[0], $identifiers, $source),
            self::grantor($source, $identifiers),
            self::behavior($source),
        );
    }

    /**
     * Each scope clause may appear once, and SCHEMAS cannot be restricted to schemas.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function defaults(Origin $origin, Node $source, Identifiers $identifiers): GrantDefaultPrivilegesStatement|RevokeDefaultPrivilegesStatement
    {
        $roles = null;
        $schemas = null;
        foreach (Tree::outer($source, ['DefACLOption']) as $option) {
            $list = Tree::child($option, ['role_list']);
            if ($list !== null && $roles === null) {
                $roles = RoleSpecs::references($list, $identifiers);
                continue;
            }
            if ($list === null && $schemas === null) {
                $schemas = PrivilegeTargets::names(self::child($option, 'name_list'), $identifiers);
                continue;
            }
            throw new InvalidSql(InputViolation::DefaultPrivilegeScope, $option);
        }
        $action = self::child($source, 'DefACLAction');
        $privileges = Privileges::read(self::child($action, 'privileges'), $identifiers);
        $class = strtoupper(Tree::text(self::child($action, 'defacl_privilege_target')));
        $target = $class === 'ROUTINES' ? DefaultPrivilegeTarget::Functions : (DefaultPrivilegeTarget::tryFrom($class) ?? throw new UnclassifiedSql('Unclassified default privilege target: ' . $class));
        if ($schemas !== null && $target === DefaultPrivilegeTarget::Schemas) {
            throw new InvalidSql(InputViolation::DefaultPrivilegeScope, $source);
        }
        self::check($target, $privileges, $action);
        $grantees = RoleSpecs::grantees(self::child($action, 'grantee_list'), $identifiers);
        $words = array_map(static fn ($token): string => strtoupper($token->text), array_slice($action->tokens(), 0, 2));
        if ($words[0] === 'GRANT') {
            return new GrantDefaultPrivilegesStatement($origin, $privileges, $target, $grantees, Tree::child($action, ['opt_grant_grant_option']) !== null, $roles ?? [], $schemas ?? []);
        }
        return new RevokeDefaultPrivilegesStatement($origin, $privileges, $target, $grantees, ($words[1] ?? '') === 'GRANT', self::behavior($action), $roles ?? [], $schemas ?? []);
    }

    /**
     * Privileges outside the object class domain are diagnosed before construction.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges
     * @throws InvalidSql
     */
    public static function check(TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets|DefaultPrivilegeTarget $target, array $privileges, Node $source): void
    {
        $violation = PrivilegeInvariant::mismatch($target, $privileges);
        if ($violation !== null) {
            throw new InvalidSql($violation, $source);
        }
    }

    /**
     * @throws InvalidSql
     */
    public static function grantor(Node $source, Identifiers $identifiers): NamedRole|SessionRole|null
    {
        $clause = Tree::child($source, ['opt_granted_by']);
        return $clause === null ? null : RoleSpecs::reference($clause, $identifiers);
    }

    /**
     * Reads the dependent-grant policy when present.
     */
    public static function behavior(Node $source): DropBehavior
    {
        $behavior = Tree::child($source, ['opt_drop_behavior']);
        return $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior)));
    }

    /**
     * @throws UnclassifiedSql
     */
    public static function child(Node $source, string $rule): Node
    {
        return Tree::child($source, [$rule]) ?? throw new UnclassifiedSql('A privilege operation requires its ' . $rule . '.');
    }
}
