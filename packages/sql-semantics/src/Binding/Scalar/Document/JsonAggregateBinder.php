<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Binding\Scalar\FunctionClauses;
use SqlSemantics\Binding\Scalar\WindowBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\SelectModifiersBinder;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectAggregate;
use SqlSemantics\Model\Scalar\Function\WindowCall;

/**
 * Binds PostgreSQL's JSON_OBJECTAGG and JSON_ARRAYAGG with their FILTER and OVER clauses.
 * @visibility SqlSemantics
 */
final class JsonAggregateBinder
{
    /**
     * Binds the aggregate owned by an invocation, wrapping it in a `WindowCall` when OVER is written.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $aggregate = $source->name === 'json_aggregate_func' ? $source : Tree::child($source, ['json_aggregate_func']);
        $keyword = $aggregate?->children[0] ?? null;
        if ($aggregate === null || !$keyword instanceof Token) {
            return null;
        }
        $filterNode = FunctionClauses::find($source, ['filter_clause']);
        $condition = $filterNode === null ? null : Tree::child($filterNode, ['a_expr']);
        $filter = $condition === null ? null : (new ExpressionBinder())->bind($condition, $scope);
        if ($filter !== null) {
            (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($filter);
        }
        $call = strtoupper($keyword->text) === 'JSON_OBJECTAGG' ? self::object($aggregate, $scope, $filter) : self::array($aggregate, $scope, $filter);
        $over = FunctionClauses::find($source, ['over_clause']);
        return $over === null ? $call : new WindowCall($call->facts, $source, $call, (new WindowBinder())->bind($over, $scope));
    }

    /**
     * Binds JSON_OBJECTAGG's member, NULL handling, key uniqueness and RETURNING type.
     * @throws InvalidSql
     */
    public static function object(Node $aggregate, Scope $scope, ?Expression $filter): JsonObjectAggregate
    {
        $member = Tree::child($aggregate, ['json_name_and_value']);
        if ($member === null) {
            Tree::invalid($aggregate, 'JSON_OBJECTAGG member');
        }
        return new JsonObjectAggregate($aggregate, SqlJsonClauses::member($member, $scope), SqlJsonClauses::nullHandling($aggregate, JsonNullHandling::Null), SqlJsonClauses::uniqueKeys($aggregate), SqlJsonClauses::returning($aggregate, $scope), $filter);
    }

    /**
     * Binds JSON_ARRAYAGG's element, element order, NULL handling and RETURNING type.
     * @throws InvalidSql
     */
    public static function array(Node $aggregate, Scope $scope, ?Expression $filter): JsonArrayAggregate
    {
        $element = Tree::child($aggregate, ['json_value_expr']);
        if ($element === null) {
            Tree::invalid($aggregate, 'JSON_ARRAYAGG element');
        }
        $order = Tree::child($aggregate, ['json_array_aggregate_order_by_clause_opt']);
        $ordering = $order === null ? [] : (new SelectModifiersBinder())->ordering($order, $scope, null);
        return new JsonArrayAggregate($aggregate, JsonOptions::input($element, $scope), $ordering, SqlJsonClauses::nullHandling($aggregate, JsonNullHandling::Absent), SqlJsonClauses::returning($aggregate, $scope), $filter);
    }
}
