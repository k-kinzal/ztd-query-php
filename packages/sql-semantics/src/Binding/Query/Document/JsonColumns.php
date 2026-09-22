<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\TableFunction\Json;

/**
 * Binds each JSON_TABLE declaration without flattening nested paths into a name list.
 * @visibility SqlSemantics
 */
final class JsonColumns
{
    /**
     * @return list<Json\Column>
     */
    public static function bind(Node $source, Scope $scope): array
    {
        return array_map(static fn (Node $node): Json\Column => self::column($node, $scope), Tree::outer($source, ['json_table_column_definition', 'jt_column']));
    }

    /**
     * Distinguishes nested expansion, ordinal generation, existence testing, and value extraction.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function column(Node $source, Scope $scope): Json\Column
    {
        $name = Tree::child($source, ['ColId', 'ident']);
        $path = Tree::child($source, ['Sconst', 'text_literal', 'json_table_column_path_clause_opt']);
        $path = $path !== null && Tree::hasTokens($path) ? (Tree::child($path, ['Sconst']) ?? $path) : null;
        $value = $path === null ? null : (new ExpressionBinder())->bind($path, $scope);
        if ($name === null) {
            $columns = Tree::child($source, ['json_table_column_definition_list', 'columns_clause']);
            if ($value === null || $columns === null) {
                Tree::invalid($source, 'nested JSON path and columns');
            }
            $alias = Tree::child($source, ['name']);
            return new Json\NestedColumns($value, self::bind($columns, $scope), $alias === null ? null : $scope->identifiers->parts($alias)[0]);
        }
        $label = $scope->identifiers->parts($name)[0];
        $typeNode = Tree::child($source, ['Typename', 'type']);
        if ($typeNode === null) {
            return new Json\Ordinality($label);
        }
        $type = (new \SqlSemantics\Ast\TypeReader($scope->identifiers->dialect))->read($typeNode);
        $collate = Tree::child($source, ['opt_collate']);
        $collation = $collate === null || !Tree::hasTokens($collate) ? null : new QualifiedName(array_slice($scope->identifiers->parts($collate), 1));
        [$empty, $error] = JsonOptions::responses($source);
        $words = array_map(static fn ($child): string => strtoupper(Tree::text($child)), Tree::significant($source));
        if (in_array('EXISTS', $words, true)) {
            if ($empty !== null) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::JsonOption, $source);
            }
            return new Json\ExistsColumn($label, $type, $value, $collation, JsonOptions::exists($error));
        }
        return new Json\ValueColumn($label, $type, $value, $collation, JsonOptions::format(Tree::child($source, ['json_format_clause'])), JsonOptions::wrapper(Tree::child($source, ['json_wrapper_behavior'])), JsonOptions::quotes(Tree::child($source, ['json_quotes_clause_opt'])), JsonOptions::response($empty, $scope), JsonOptions::response($error, $scope));
    }
}
