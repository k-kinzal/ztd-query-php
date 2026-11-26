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
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One checked prefix operation with its scoped input.
 *
 * @visibility public
 * @example Preserving a unary plus on a read
 *     $field = new \SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0);
 *     $unary = new \SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression(\SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind::Plus, $field);
 *     $unary->operand->slot // => 0
 */
final class UnaryExpression
{
    use Snapshot;

    /**
     * Stores the selected operation and its operand.
     */
    public function __construct(
        public readonly UnaryKind $kind,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|self $operand,
    ) {
        if ($kind === UnaryKind::Minus) {
            $literal = $operand;
            while ($literal instanceof self && $literal->kind === UnaryKind::Plus) {
                $literal = $literal->operand;
            }
            Check::input(!$literal instanceof HexIntegerLiteral || $literal->digits !== '8000000000000000', 'The minimum hexadecimal INTEGER cannot be negated.');
        }
    }
}
