<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

/**
 * Built-in comparison operations accepted by quantified subqueries.
 * @visibility public
 * @example Reading the operator of a quantified comparison
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 <> ALL (SELECT 2)');
 *     $query->outputs[0]->expression->operator->value // => '<>'
 */
enum ComparisonOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case NotEqualBang = '!=';
    case Less = '<';
    case Greater = '>';
    case LessEqual = '<=';
    case GreaterEqual = '>=';
    case NullSafeEqual = '<=>';
}
