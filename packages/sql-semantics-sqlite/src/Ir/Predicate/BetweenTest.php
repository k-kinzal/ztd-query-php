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
 * A range test with one tested scalar and two inclusive bounds.
 *
 * @visibility public
 * @example Keeping the written bound positions
 *     $low = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('2');
 *     $high = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('4');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest($low, $low, $high))->upper->decimal // => '4'
 */
final class BetweenTest
{
    use Snapshot;

    /**
     * Holds the tested scalar, both bound roles and the negation.
     */
    public function __construct(
        public readonly Arithmetic|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|self|TruthTest|UnaryExpression $operand,
        public readonly Arithmetic|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|self|TruthTest|UnaryExpression $lower,
        public readonly Arithmetic|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|self|TruthTest|UnaryExpression $upper,
        public readonly bool $negated = false,
    ) {
    }
}
