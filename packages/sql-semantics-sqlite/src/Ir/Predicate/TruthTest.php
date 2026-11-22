<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A Boolean test with a resolved truth value.
 *
 * @visibility public
 * @example Representing an explicit test for false
 *     $test = new \SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest(new \SqlSemantics\Platform\Sqlite\Ir\Parameter('2'), false);
 *     $test->desired // => false
 */
final class TruthTest
{
    use Snapshot;

    /**
     * Stores the operand, the requested Boolean value and the negation flag.
     */
    public function __construct(
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|self|UnaryExpression $operand,
        public readonly bool $desired,
        public readonly bool $negated = false,
    ) {
    }
}
