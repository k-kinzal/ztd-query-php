<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A projected expression and its observable output name.
 *
 * @visibility public
 * @example Naming an explicit NULL projection
 *     $column = new \SqlSemantics\Platform\Sqlite\Ir\ResultColumn(new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral(), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('value'));
 *     $column->name->value // => 'value'
 */
final class ResultColumn
{
    use Snapshot;

    /**
     * Pairs a closed expression with its semantic result name.
     */
    public function __construct(
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $expression,
        public readonly Identifier $name,
    ) {
    }
}
