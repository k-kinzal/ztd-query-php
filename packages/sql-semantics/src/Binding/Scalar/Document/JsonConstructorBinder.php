<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayQuery;
use SqlSemantics\Model\Scalar\Document\Construction\JsonMember;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor;
use SqlSemantics\Model\Scalar\Document\JsonParse;
use SqlSemantics\Model\Scalar\Document\JsonScalarConversion;
use SqlSemantics\Model\Scalar\Document\JsonSerialization;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\RowShape;

/**
 * Binds PostgreSQL's SQL/JSON constructors and conversions: JSON_OBJECT, JSON_ARRAY, JSON, JSON_SCALAR and JSON_SERIALIZE.
 * @visibility SqlSemantics
 */
final class JsonConstructorBinder
{
    /**
     * Binds the SQL/JSON JSON_OBJECT forms; the legacy `json_object(text[])` call is left to function resolution.
     * @throws InvalidSql
     */
    public static function object(Node $source, Scope $scope): ?JsonObjectConstructor
    {
        if (Tree::child($source, ['func_arg_list']) !== null) {
            return null;
        }
        $list = Tree::child($source, ['json_name_and_value_list']);
        $members = $list === null ? [] : array_map(static fn (Node $member): JsonMember => SqlJsonClauses::member($member, $scope), Tree::outer($list, ['json_name_and_value']));
        return new JsonObjectConstructor($source, $members, SqlJsonClauses::nullHandling($source, JsonNullHandling::Null), SqlJsonClauses::uniqueKeys($source), SqlJsonClauses::returning($source, $scope));
    }

    /**
     * Binds JSON_ARRAY over values or over a query; the query must produce one column.
     * @throws InvalidSql
     */
    public static function array(Node $source, Scope $scope): ?Expression
    {
        $query = Tree::child($source, ['select_no_parens']);
        if ($query !== null) {
            if ($scope->queries === null) {
                return null;
            }
            $bound = $scope->queries->bind($query, $scope);
            $width = RowShape::width($bound);
            if ($width !== null && $width !== 1) {
                throw new InvalidSql(InputViolation::ScalarQueryWidth, $query);
            }
            $format = Tree::child($source, ['json_format_clause_opt']);
            return new JsonArrayQuery($source, $bound, JsonOptions::format($format === null ? null : Tree::child($format, ['json_format_clause'])), SqlJsonClauses::returning($source, $scope));
        }
        $list = Tree::child($source, ['json_value_expr_list']);
        $elements = $list === null ? [] : array_map(static fn (Node $element): Input => JsonOptions::input($element, $scope), Tree::outer($list, ['json_value_expr']));
        return new JsonArrayConstructor($source, $elements, SqlJsonClauses::nullHandling($source, JsonNullHandling::Absent), SqlJsonClauses::returning($source, $scope));
    }

    /**
     * Binds JSON(), JSON_SCALAR and JSON_SERIALIZE by their leading keyword.
     * @throws InvalidSql
     */
    public static function conversion(string $keyword, Node $source, Scope $scope): ?Expression
    {
        $value = Tree::child($source, ['json_value_expr']);
        if ($keyword === 'JSON_SCALAR') {
            $scalar = Tree::child($source, ['a_expr']);
            return $scalar === null ? null : new JsonScalarConversion($source, (new ExpressionBinder())->bind($scalar, $scope));
        }
        if ($value === null) {
            return null;
        }
        $input = JsonOptions::input($value, $scope);
        return $keyword === 'JSON' ? new JsonParse($source, $input, SqlJsonClauses::uniqueKeys($source)) : new JsonSerialization($source, $input, SqlJsonClauses::returning($source, $scope));
    }
}
