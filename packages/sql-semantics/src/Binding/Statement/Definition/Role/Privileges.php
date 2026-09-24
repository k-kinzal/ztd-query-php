<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads privilege lists, which the grammar shares between object privileges and role names.
 * @visibility SqlSemantics
 */
final class Privileges
{
    /**
     * @return non-empty-list<ObjectPrivilege|ColumnPrivilege>
     * @throws InvalidSql
     */
    public static function read(Node $privileges, Identifiers $identifiers): array
    {
        $list = Tree::child($privileges, ['privilege_list']);
        if ($list === null) {
            $columns = Tree::child($privileges, ['columnList']);
            return [$columns === null ? new ObjectPrivilege(Privilege::All) : new ColumnPrivilege(Privilege::All, self::columns($columns, $identifiers))];
        }
        return Collections::nonEmpty(array_map(static fn (Node $privilege): ObjectPrivilege|ColumnPrivilege => self::privilege($privilege, $identifiers), Tree::outer($list, ['privilege'])));
    }

    /**
     * A column list is accepted only by the privileges that have a column form.
     * @throws InvalidSql
     */
    public static function privilege(Node $source, Identifiers $identifiers): ObjectPrivilege|ColumnPrivilege
    {
        $privilege = self::type(self::name($source, $identifiers), $source);
        $columns = Tree::child($source, ['opt_column_list']);
        if ($columns === null) {
            return new ObjectPrivilege($privilege);
        }
        if (!$privilege->columnar()) {
            throw new InvalidSql(InputViolation::ColumnPrivilege, $source);
        }
        return new ColumnPrivilege($privilege, self::columns($columns, $identifiers));
    }

    /**
     * Keyword privileges have fixed lowercase names; identifier privileges keep their case.
     */
    public static function name(Node $source, Identifiers $identifiers): string
    {
        $column = Tree::child($source, ['ColId']);
        if ($column !== null) {
            return $identifiers->name($column->tokens()[0]);
        }
        $first = $source->tokens()[0];
        return strtoupper($first->text) === 'ALTER' ? 'alter system' : strtolower($first->text);
    }

    /**
     * Privilege names are matched as the server matches them, after identifier folding.
     * @throws InvalidSql
     */
    public static function type(string $name, Node $source): Privilege
    {
        return match ($name) {
            'select' => Privilege::Select,
            'insert' => Privilege::Insert,
            'update' => Privilege::Update,
            'delete' => Privilege::Delete,
            'truncate' => Privilege::Truncate,
            'references' => Privilege::References,
            'trigger' => Privilege::Trigger,
            'execute' => Privilege::Execute,
            'usage' => Privilege::Usage,
            'create' => Privilege::Create,
            'connect' => Privilege::Connect,
            'temporary', 'temp' => Privilege::Temporary,
            'set' => Privilege::Set,
            'alter system' => Privilege::AlterSystem,
            'maintain' => Privilege::Maintain,
            default => throw new InvalidSql(InputViolation::PrivilegeName, $source),
        };
    }

    /**
     * @return non-empty-list<string>
     */
    public static function columns(Node $list, Identifiers $identifiers): array
    {
        return Collections::nonEmpty(array_map(static fn (Node $column): string => $identifiers->name($column->tokens()[0]), Tree::outer($list, ['columnElem'])));
    }

    /**
     * In a membership grant the privilege list names roles and cannot carry columns.
     * @return non-empty-list<NamedRole>
     * @throws InvalidSql
     */
    public static function roles(Node $list, Identifiers $identifiers): array
    {
        $roles = [];
        foreach (Tree::outer($list, ['privilege']) as $privilege) {
            if (Tree::child($privilege, ['opt_column_list']) !== null) {
                throw new InvalidSql(InputViolation::ColumnPrivilege, $privilege);
            }
            $name = self::name($privilege, $identifiers);
            if (in_array($name, ['', 'public', 'none'], true)) {
                throw new InvalidSql(InputViolation::RoleReference, $privilege);
            }
            $roles[] = new NamedRole($name);
        }
        return Collections::nonEmpty($roles);
    }

    /**
     * @return list<RoleGrantOption>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function options(?Node $list, Identifiers $identifiers): array
    {
        if ($list === null) {
            return [];
        }
        $options = [];
        foreach (Tree::outer($list, ['grant_role_opt']) as $option) {
            $label = Tree::child($option, ['ColLabel']) ?? throw new UnclassifiedSql('A membership option requires its name.');
            $value = Tree::child($option, ['grant_role_opt_value']) ?? throw new UnclassifiedSql('A membership option requires its value.');
            $options[] = new RoleGrantOption(self::attribute($label->tokens()[0], $identifiers, $option), strtoupper(Tree::text($value)) !== 'FALSE');
        }
        return $options;
    }

    /**
     * Membership options match only their lowercase spelling, as the server compares them.
     * @throws InvalidSql
     */
    public static function attribute(Token $token, Identifiers $identifiers, Node $source): RoleGrantAttribute
    {
        return match ($identifiers->name($token)) {
            'admin' => RoleGrantAttribute::Admin,
            'inherit' => RoleGrantAttribute::Inherit,
            'set' => RoleGrantAttribute::Set,
            default => throw new InvalidSql(InputViolation::RoleGrantOption, $source),
        };
    }
}
