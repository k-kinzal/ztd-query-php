<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Separates privilege lists from role lists; MySQL 8 spells both with the same grammar rule.
 * @visibility SqlSemantics
 */
final class PrivilegeLists
{
    /**
     * Static keywords, column-restricted keywords, and bare identifiers as dynamic privileges; roles with hosts are impossible here.
     * @return non-empty-list<StaticPrivilege|ColumnPrivilege|DynamicPrivilege>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function privileges(Node $list, Identifiers $identifiers): array
    {
        $privileges = [];
        foreach (Tree::outer($list, ['role_or_privilege', 'object_privilege']) as $element) {
            $columns = Tree::child($element, ['opt_column_list']);
            $name = Tree::child($element, ['role_ident_or_text']);
            if ($name !== null) {
                if ($columns !== null) {
                    throw new InvalidSql(InputViolation::ColumnPrivilege, $element);
                }
                if (count($element->tokens()) > 1) {
                    throw new InvalidSql(InputViolation::RoleGrant, $element);
                }
                $privileges[] = self::dynamic($element, AccountNames::part($name->tokens()[0], $identifiers));
                continue;
            }
            $privilege = StaticPrivilege::tryFrom(self::keyword($element, $columns)) ?? throw new UnclassifiedSql('Unclassified privilege: ' . Tree::text($element));
            $privileges[] = $columns === null ? $privilege : self::columns($element, $privilege, $columns, $identifiers);
        }
        if ($privileges === []) {
            throw new UnclassifiedSql('A privilege list requires at least one privilege.');
        }
        return Collections::nonEmpty($privileges);
    }

    /**
     * Role names with optional hosts; privilege keywords and column lists are impossible in a role list.
     * @return non-empty-list<AccountName>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function roles(Node $list, Identifiers $identifiers): array
    {
        $roles = [];
        foreach (Tree::outer($list, ['role_or_privilege']) as $element) {
            $name = Tree::child($element, ['role_ident_or_text']);
            if ($name === null || Tree::child($element, ['opt_column_list']) !== null) {
                throw new InvalidSql(InputViolation::RoleGrant, $element);
            }
            $tokens = $element->tokens();
            $roles[] = new AccountName(AccountNames::part($tokens[0], $identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $identifiers) : null);
        }
        if ($roles === []) {
            throw new UnclassifiedSql('A role list requires at least one role.');
        }
        return Collections::nonEmpty($roles);
    }

    /**
     * The keyword spelling of a static privilege, excluding any column list; the SCHEMAS synonym reads as DATABASES.
     */
    public static function keyword(Node $element, ?Node $columns): string
    {
        $words = [];
        foreach (Tree::significant($element) as $child) {
            if ($child === $columns) {
                break;
            }
            $word = strtoupper(Tree::text($child));
            $words[] = $word === 'SCHEMAS' ? 'DATABASES' : $word;
        }
        return implode(' ', $words);
    }

    /**
     * @throws InvalidSql
     */
    public static function columns(Node $element, StaticPrivilege $privilege, Node $columns, Identifiers $identifiers): ColumnPrivilege
    {
        $names = array_map(static fn (Node $column): string => $identifiers->name($column->tokens()[0]), Tree::outer($columns, ['ident']));
        try {
            return new ColumnPrivilege($privilege, Collections::nonEmpty($names));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ColumnPrivilege, $element, $error);
        }
    }

    /**
     * @throws InvalidSql
     */
    public static function dynamic(Node $element, string $name): DynamicPrivilege
    {
        try {
            return new DynamicPrivilege($name);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PrivilegeLevel, $element, $error);
        }
    }

    /**
     * Whether the list is spelled ALL [PRIVILEGES] rather than named privileges.
     */
    public static function all(Node $statement): bool
    {
        foreach (Tree::significant($statement) as $child) {
            if ($child instanceof Token && strtoupper($child->text) === 'ALL') {
                return true;
            }
            if ($child instanceof Node && $child->name === 'grant_privileges' && strtoupper($child->tokens()[0]->text) === 'ALL') {
                return true;
            }
        }
        return false;
    }
}
