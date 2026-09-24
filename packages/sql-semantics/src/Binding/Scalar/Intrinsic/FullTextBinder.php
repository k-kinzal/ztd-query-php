<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Text\FullTextMode;
use SqlSemantics\Model\Scalar\Text\FullTextSearch;

/**
 * Retains the searched columns, search string and modifier of MySQL MATCH ... AGAINST.
 * @visibility SqlSemantics
 */
final class FullTextBinder
{
    /**
     * Recognizes MATCH by its column list rather than by a function name.
     */
    public static function bind(Node $source, Scope $scope): ?FullTextSearch
    {
        $first = $source->children[0] ?? null;
        $list = Tree::child($source, ['ident_list_arg']);
        $query = Tree::child($source, ['bit_expr']);
        if ($scope->identifiers->dialect !== Dialect::MySql || !$first instanceof Token || strtoupper($first->text) !== 'MATCH' || $list === null || $query === null) {
            return null;
        }
        $columns = array_map(static fn (Node $column) => $scope->column($scope->identifiers->parts($column), $column), Tree::outer($list, ['simple_ident']));
        return new FullTextSearch($source, $columns, (new ExpressionBinder())->bind($query, $scope), self::mode(Tree::child($source, ['fulltext_options'])));
    }

    /**
     * Classifies the search modifier; an omitted modifier is natural-language mode.
     */
    public static function mode(?Node $options): FullTextMode
    {
        $text = $options === null ? '' : strtoupper(Tree::text($options));
        return match (true) {
            str_contains($text, 'BOOLEAN') => FullTextMode::Boolean,
            str_contains($text, 'EXPANSION') => FullTextMode::QueryExpansion,
            default => FullTextMode::NaturalLanguage,
        };
    }
}
