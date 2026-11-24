<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A default expression that can evaluate without a row or live parameter.
 *
 * @visibility public
 * @example Reading a checked computed column default
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $semantics->analyze('CREATE TABLE entries (amount INTEGER DEFAULT (2 + 3))')->columns[0]->default instanceof \SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression // => true
 */
final class ColumnDefaultExpression
{
    use Snapshot;

    /**
     * Checks the full operand graph against the empty relation and live parameter scope.
     */
    public function __construct(
        public readonly Arithmetic|CaseExpression|CastExpression|Coalesce|Concatenation|FoldedMembership|UnaryExpression $expression,
    ) {
        (new ExpressionScope())->check([], [[$expression, 0, 0]], false);
    }
}
