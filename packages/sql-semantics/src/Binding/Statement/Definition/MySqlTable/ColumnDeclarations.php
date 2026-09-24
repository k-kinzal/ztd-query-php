<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Declaration\ColumnDefinition as ParsedColumn;
use SqlSemantics\Ast\Declaration\TableConstraint as ParsedConstraint;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Schema\ColumnBinder;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Reads the column declarations written in ALTER TABLE items, across the grammar shapes of every MySQL release.
 * @visibility SqlSemantics
 */
final class ColumnDeclarations
{
    /**
     * Returns the declaration node of an ADD, CHANGE, or MODIFY item, or null when the item declares no single column.
     * The declared name is reduced to its last identifier because MySQL 5 accepts qualified column names.
     */
    public static function node(Node $item): ?Node
    {
        $children = [];
        foreach ($item->children as $child) {
            $expand = $child instanceof Node && in_array($child->name, ['column_def', 'field_spec'], true);
            array_push($children, ...($expand ? self::flatten($child) : [$child]));
        }
        if (array_filter($children, static fn ($child): bool => $child instanceof Node && in_array($child->name, ['field_def', 'type'], true)) === []) {
            return null;
        }
        $names = array_keys(array_filter($children, static fn ($child): bool => $child instanceof Node && in_array($child->name, ['ident', 'field_ident'], true)));
        if ($names === []) {
            return null;
        }
        $position = $names[count($names) - 1];
        $name = $children[$position];
        $identifiers = $name instanceof Node ? Tree::outer($name, ['ident']) : [];
        $declaration = [$identifiers === [] ? $name : $identifiers[count($identifiers) - 1], ...array_slice($children, $position + 1)];
        return new Node('column_def', $item->ordinal, array_values(array_filter($declaration, static fn ($child): bool => !$child instanceof Node || $child->name !== 'opt_place')));
    }

    /**
     * Expands a MySQL 5 column_def into the children of its field_spec followed by its trailing clauses.
     * @return list<Node|\SqlParser\Lexer\Token>
     */
    public static function flatten(Node $declaration): array
    {
        $children = [];
        foreach ($declaration->children as $child) {
            array_push($children, ...($child instanceof Node && $child->name === 'field_spec' ? $child->children : [$child]));
        }
        return $children;
    }

    /**
     * Parses a declaration node with its column attributes.
     * @return array{ParsedColumn, list<ParsedConstraint>}
     */
    public static function parse(Node $column, Scope $scope): array
    {
        return (new ColumnReader($scope->identifiers))->read($column, Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute', 'gcol_attribute']));
    }

    /**
     * Binds a declaration node and the integrity constraints written on it.
     * @return array{ColumnDefinition, list<TableConstraint>}
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Node $column, Scope $scope): array
    {
        [$parsed, $constraints] = self::parse($column, $scope);
        return [ColumnBinder::bind($parsed, $scope), array_map(static fn (ParsedConstraint $constraint): TableConstraint => ConstraintBinder::bind($constraint, $scope), $constraints)];
    }

    /**
     * Lists the table's columns followed by every column declared by ADD, CHANGE, or MODIFY items, a redeclared name replacing the earlier column.
     * @param list<Node> $items
     * @param list<ColumnDefinition> $columns Columns of the altered table
     * @return list<ColumnDefinition>
     */
    public static function declared(array $items, Scope $scope, array $columns = []): array
    {
        foreach ($items as $item) {
            $nodes = $item->name === 'alter_list_item' ? [self::node($item)] : [];
            $list = Tree::child($item, ['table_element_list', 'create_field_list']);
            if ($list !== null) {
                $nodes = Tree::outer($list, ['column_def']);
            }
            foreach (array_filter($nodes) as $node) {
                $parsed = self::parse($node, $scope)[0];
                $columns = array_values(array_filter($columns, static fn (ColumnDefinition $column): bool => strtolower($column->name) !== strtolower($parsed->name)));
                $columns[] = new ColumnDefinition($parsed->name, $parsed->type, $parsed->nullability, $parsed->source);
            }
        }
        return $columns;
    }
}
