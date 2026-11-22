<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Appends the right scalar's text to the left scalar's text.
 *
 * @visibility public
 * @example Keeping the left and right operands in order
 *     $left = new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral('left');
 *     $right = new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral('right');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation($left, $right))->right->value // => 'right'
 */
final class Concatenation
{
    use Snapshot;

    /**
     * Holds both ordered scalar operands.
     */
    public function __construct(
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|self|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $left,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|self|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $right,
    ) {
    }
}
