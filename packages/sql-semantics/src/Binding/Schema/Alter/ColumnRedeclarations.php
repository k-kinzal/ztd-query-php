<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnDeclarations;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Applies a MySQL ADD, CHANGE, or MODIFY item to the columns of a table snapshot: the written declaration replaces the
 * changed column where it stood, or joins the table, at the FIRST or AFTER position when one is written.
 * @visibility SqlSemantics
 */
final class ColumnRedeclarations
{
    /**
     * Returns the columns after the item and the constraints its declarations write, or null for an item that declares no column.
     * @param list<ColumnDefinition> $columns
     * @return array{list<ColumnDefinition>, list<TableConstraint>}|null
     * @throws \SqlSemantics\InvalidSql
     */
    public static function apply(Node $item, array $columns, Scope $scope): ?array
    {
        $declarations = ColumnDeclarations::written([$item]);
        if ($declarations === []) {
            return null;
        }
        $replaced = strtoupper($item->tokens()[0]->text ?? '') === 'ADD' ? null : self::replaced($item, $scope->identifiers);
        $constraints = [];
        foreach ($declarations as $declaration) {
            [$column, $local] = ColumnDeclarations::bind($declaration, $scope);
            array_push($constraints, ...$local);
            $columns = self::place($columns, $column, $replaced, Tree::child($item, ['opt_place']), $scope->identifiers);
        }
        return [$columns, $constraints];
    }

    /**
     * Returns the name of the column a CHANGE or MODIFY item replaces, the last part of a qualified MySQL 5 name.
     */
    public static function replaced(Node $item, Identifiers $identifiers): ?string
    {
        $name = Tree::child($item, ['field_ident', 'ident']);
        if ($name === null) {
            return null;
        }
        $parts = $identifiers->parts($name);
        return $parts[count($parts) - 1];
    }

    /**
     * Puts the column where the replaced column stood, or last, unless the position writes FIRST or AFTER a column.
     * @param list<ColumnDefinition> $columns
     * @return list<ColumnDefinition>
     */
    public static function place(array $columns, ColumnDefinition $column, ?string $replaced, ?Node $position, Identifiers $identifiers): array
    {
        $index = count($columns);
        foreach ($columns as $key => $existing) {
            if ($replaced !== null && $identifiers->equal($existing->name, $replaced)) {
                $index = $key;
                unset($columns[$key]);
                break;
            }
        }
        $columns = array_values($columns);
        $tokens = $position?->tokens() ?? [];
        if (strtoupper($tokens[0]->text ?? '') === 'FIRST') {
            $index = 0;
        }
        $after = strtoupper($tokens[0]->text ?? '') === 'AFTER' && $position !== null ? Tree::child($position, ['ident']) : null;
        if ($after !== null) {
            $name = $identifiers->parts($after)[0];
            foreach ($columns as $key => $existing) {
                if ($identifiers->equal($existing->name, $name)) {
                    $index = $key + 1;
                }
            }
        }
        array_splice($columns, $index, 0, [$column]);
        return $columns;
    }
}
