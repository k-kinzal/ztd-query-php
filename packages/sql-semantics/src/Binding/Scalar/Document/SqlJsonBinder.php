<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;

/**
 * Recognizes the SQL/JSON functions by their keyword and hands each to its operand binder.
 * @visibility SqlSemantics
 */
final class SqlJsonBinder
{
    /**
     * Returns null for any other expression, including the legacy `json_object(text[])` call; MySQL's JSON_VALUE has its own binder.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        if ($scope->identifiers->dialect !== Dialect::PostgreSql) {
            return JsonValueBinder::bind($source, $scope);
        }
        if ($source->name === 'json_aggregate_func' || $source->name === 'func_expr') {
            return JsonAggregateBinder::bind($source, $scope);
        }
        $first = $source->children[0] ?? null;
        if ($source->name !== 'func_expr_common_subexpr' || !$first instanceof Token) {
            return null;
        }
        $keyword = strtoupper($first->text);
        return match ($keyword) {
            'JSON_VALUE' => JsonQueryBinder::value($source, $scope),
            'JSON_QUERY' => JsonQueryBinder::query($source, $scope),
            'JSON_EXISTS' => JsonQueryBinder::exists($source, $scope),
            'JSON_OBJECT' => JsonConstructorBinder::object($source, $scope),
            'JSON_ARRAY' => JsonConstructorBinder::array($source, $scope),
            'JSON', 'JSON_SCALAR', 'JSON_SERIALIZE' => JsonConstructorBinder::conversion($keyword, $source, $scope),
            default => null,
        };
    }
}
