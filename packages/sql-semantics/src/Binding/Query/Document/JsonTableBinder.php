<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\TableFunction\Json;

/**
 * Binds JSON_TABLE's document, row path, path variables, and explicit output declarations.
 * @visibility SqlSemantics
 */
final class JsonTableBinder
{
    /**
     * Retains the entire row-expansion operation as semantic data.
     */
    public static function bind(Node $source, Scope $scope): Json\JsonTable
    {
        $document = Tree::child($source, ['json_value_expr', 'expr']);
        $path = Tree::child($source, ['a_expr', 'text_literal']);
        $columns = Tree::child($source, ['json_table_column_definition_list', 'columns_clause']);
        if ($document === null || $path === null || $columns === null) {
            Tree::invalid($source, 'JSON_TABLE document, row path and column declarations');
        }
        $pathName = Tree::child($source, ['json_table_path_name_opt']);
        $name = $pathName === null ? null : Tree::child($pathName, ['name']);
        $passing = Tree::child($source, ['json_passing_clause_opt']);
        $arguments = $passing === null ? [] : array_map(static fn (Node $node): Json\PassingArgument => self::argument($node, $scope), Tree::outer($passing, ['json_argument']));
        return new Json\JsonTable(JsonOptions::input($document, $scope), (new ExpressionBinder())->bind($path, $scope), JsonColumns::bind($columns, $scope), $name === null ? null : $scope->identifiers->parts($name)[0], $arguments, JsonOptions::tableError(JsonOptions::responses($source)[1]));
    }

    /**
     * Binds one path variable to its supplied SQL expression and document format.
     */
    public static function argument(Node $source, Scope $scope): Json\PassingArgument
    {
        $name = Tree::child($source, ['ColLabel']);
        $input = Tree::child($source, ['json_value_expr']);
        if ($name === null || $input === null) {
            Tree::invalid($source, 'JSON PASSING name and value');
        }
        return new Json\PassingArgument($scope->identifiers->parts($name)[0], JsonOptions::input($input, $scope));
    }
}
