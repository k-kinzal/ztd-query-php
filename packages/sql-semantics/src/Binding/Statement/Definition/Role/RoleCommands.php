<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Role\RoleKeyword;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AddGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\RenameRoleStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes PostgreSQL role definitions, alterations, and privilege operations.
 * @visibility SqlSemantics
 */
final class RoleCommands
{
    /**
     * Returns null for other dialects and for statements outside the role and privilege families.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        return match ($source->name) {
            'CreateRoleStmt', 'CreateUserStmt', 'CreateGroupStmt' => self::create($origin, $source, $identifiers),
            'AlterRoleStmt' => new AlterRoleStatement($origin, RoleSpecs::reference(self::spec($source), $identifiers), RoleOptions::alteration(Tree::child($source, ['AlterOptRoleList']), $identifiers)),
            'AlterRoleSetStmt' => RoleSettings::bind($origin, $source, $context),
            'AlterGroupStmt' => self::group($origin, $source, $identifiers),
            'DropRoleStmt' => new DropRolesStatement($origin, RoleSpecs::names(self::list($source), $identifiers), ($source->tokens()[2]->name ?? '') === 'IF_P'),
            'RenameStmt' => self::rename($origin, $source, $identifiers),
            default => PrivilegeCommands::bind($origin, $source, $context),
        };
    }

    /**
     * CREATE USER and CREATE GROUP keep their keyword because it changes the LOGIN default.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function create(Origin $origin, Node $source, Identifiers $identifiers): CreateRoleStatement
    {
        $name = Tree::child($source, ['RoleId']) ?? throw new UnclassifiedSql('A role definition requires its name.');
        $keyword = RoleKeyword::tryFrom(strtoupper($source->tokens()[1]->text)) ?? throw new UnclassifiedSql('Unclassified role definition keyword.');
        return new CreateRoleStatement($origin, RoleSpecs::name($name, $identifiers), RoleOptions::definition(Tree::child($source, ['OptRoleList']), $identifiers), $keyword);
    }

    /**
     * The legacy group form separates ADD USER from DROP USER.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function group(Origin $origin, Node $source, Identifiers $identifiers): AddGroupMembersStatement|DropGroupMembersStatement
    {
        $group = RoleSpecs::reference(self::spec($source), $identifiers);
        $members = RoleSpecs::references(self::list($source), $identifiers);
        $action = Tree::child($source, ['add_drop']) ?? throw new UnclassifiedSql('A group membership change requires ADD or DROP.');
        return strtoupper(Tree::text($action)) === 'ADD' ? new AddGroupMembersStatement($origin, $group, $members) : new DropGroupMembersStatement($origin, $group, $members);
    }

    /**
     * Only role, user, and group renames belong to this family.
     * @throws InvalidSql
     */
    public static function rename(Origin $origin, Node $source, Identifiers $identifiers): ?RenameRoleStatement
    {
        $names = Tree::outer($source, ['RoleId']);
        if (count($names) !== 2 || !in_array($source->tokens()[1]->name ?? '', ['ROLE', 'USER', 'GROUP_P'], true)) {
            return null;
        }
        return new RenameRoleStatement($origin, RoleSpecs::name($names[0], $identifiers), RoleSpecs::name($names[1], $identifiers));
    }

    /**
     * @throws UnclassifiedSql
     */
    public static function spec(Node $source): Node
    {
        return Tree::child($source, ['RoleSpec']) ?? throw new UnclassifiedSql('A role operation requires its role specification.');
    }

    /**
     * @throws UnclassifiedSql
     */
    public static function list(Node $source): Node
    {
        return Tree::child($source, ['role_list']) ?? throw new UnclassifiedSql('A role operation requires its role list.');
    }
}
