<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\MySqlTable\Column;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the column alterations of MySQL ALTER TABLE: ADD, CHANGE, MODIFY, DROP, ALTER COLUMN, and RENAME COLUMN.
 * @visibility SqlSemantics
 */
final class ColumnAlterations
{
    /**
     * Binds ADD [COLUMN] with one declaration or a parenthesized list.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(Node $item, Scope $scope, KeyAlterations $keys): TableAlteration
    {
        $list = Tree::child($item, ['table_element_list', 'create_field_list']);
        if ($list !== null) {
            $columns = [];
            $constraints = [];
            foreach (Tree::outer($list, ['column_def']) as $node) {
                [$columns[], $local] = ColumnDeclarations::bind($node, $scope);
                array_push($constraints, ...$local);
            }
            array_push($constraints, ...$keys->constraints($list, $scope));
            $indexes = $keys->indexes($list, $scope);
            return PartitionDefinitions::build(static fn (): Column\AddColumns => new Column\AddColumns(Collections::nonEmpty($columns), $constraints, $indexes), $item, InputViolation::TableAlteration);
        }
        $node = ColumnDeclarations::node($item) ?? throw new UnclassifiedSql('ADD COLUMN requires a declaration.');
        [$column, $constraints] = ColumnDeclarations::bind($node, $scope);
        return PartitionDefinitions::build(static fn (): Column\AddColumn => new Column\AddColumn($column, $constraints, self::position($item, $scope)), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds CHANGE [COLUMN] old new declaration, or MODIFY [COLUMN] name declaration.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function redeclare(Node $item, Scope $scope, bool $change): TableAlteration
    {
        $node = ColumnDeclarations::node($item) ?? throw new UnclassifiedSql('A column redeclaration requires a declaration.');
        [$column, $constraints] = ColumnDeclarations::bind($node, $scope);
        $position = self::position($item, $scope);
        $old = self::name($item, $scope);
        return PartitionDefinitions::build(static fn (): TableAlteration => $change ? new Column\ChangeColumn($old, $column, $constraints, $position) : new Column\ModifyColumn($column, $constraints, $position), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds DROP [COLUMN] name [RESTRICT | CASCADE].
     * @throws InvalidSql
     */
    public static function drop(Node $item, Scope $scope): Column\DropColumn
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $item->tokens());
        $behavior = DropBehavior::tryFrom((string) end($words)) ?? DropBehavior::Default;
        return PartitionDefinitions::build(static fn (): Column\DropColumn => new Column\DropColumn(self::name($item, $scope), $behavior), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds ALTER [COLUMN] name SET DEFAULT, DROP DEFAULT, or SET VISIBLE and INVISIBLE.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Node $item, Scope $scope): TableAlteration
    {
        $name = self::name($item, $scope);
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $item->tokens());
        $last = (string) end($words);
        if (in_array($last, ['VISIBLE', 'INVISIBLE'], true) && $words[count($words) - 2] === 'SET') {
            return PartitionDefinitions::build(static fn (): Column\SetColumnVisibility => new Column\SetColumnVisibility($name, $last === 'VISIBLE'), $item, InputViolation::TableAlteration);
        }
        if (array_slice($words, -2) === ['DROP', 'DEFAULT']) {
            return PartitionDefinitions::build(static fn (): Column\ColumnDefaultRemoval => new Column\ColumnDefaultRemoval($name), $item, InputViolation::TableAlteration);
        }
        $value = Tree::child($item, ['expr', 'signed_literal_or_null', 'signed_literal']) ?? throw new UnclassifiedSql('SET DEFAULT requires a value.');
        $default = (new ExpressionBinder())->bind($value, $scope);
        return PartitionDefinitions::build(static fn (): Column\ColumnDefaultAssignment => new Column\ColumnDefaultAssignment($name, $default), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds RENAME COLUMN old TO new.
     * @throws InvalidSql
     */
    public static function rename(Node $item, Scope $scope): Column\RenameColumn
    {
        $names = self::names($item, $scope);
        return PartitionDefinitions::build(static fn (): Column\RenameColumn => new Column\RenameColumn($names[0] ?? '', $names[1] ?? ''), $item, InputViolation::TableAlteration);
    }

    /**
     * Reads FIRST or AFTER name.
     * @throws InvalidSql
     */
    public static function position(Node $item, Scope $scope): Column\FirstColumn|Column\AfterColumn|null
    {
        $place = Tree::child($item, ['opt_place']);
        if ($place === null) {
            return null;
        }
        $after = Tree::child($place, ['ident']);
        return $after === null ? Column\FirstColumn::First : PartitionDefinitions::build(static fn (): Column\AfterColumn => new Column\AfterColumn($scope->identifiers->name($after->tokens()[0])), $place, InputViolation::TableAlteration);
    }

    /**
     * Reads the first column name of an item.
     */
    public static function name(Node $item, Scope $scope): string
    {
        return self::names($item, $scope)[0] ?? '';
    }

    /**
     * Reads the column names written directly in an item.
     * @return list<string>
     */
    public static function names(Node $item, Scope $scope): array
    {
        $names = array_filter($item->children, static fn ($child): bool => $child instanceof Node && in_array($child->name, ['ident', 'field_ident'], true));
        return array_values(array_map(static function (Node $name) use ($scope): string {
            $parts = $scope->identifiers->parts($name);
            return $parts[count($parts) - 1] ?? '';
        }, $names));
    }
}
