<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
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
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;

/**
 * Checks the closed scalar roles available in a VALUES row.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ValueRowOperand
{
    /**
     * Returns a checked scalar for the enclosing row scope.
     * @throws InvalidConstruction When the value is outside the row operand roles
     */
    public function checked(object $value): Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression
    {
        if ($value instanceof Arithmetic || $value instanceof BetweenTest || $value instanceof CaseExpression
            || $value instanceof CastExpression || $value instanceof Coalesce || $value instanceof CollatedExpression
            || $value instanceof Comparison || $value instanceof Concatenation || $value instanceof FoldedMembership
            || $value instanceof Logical || $value instanceof MembershipTest || $value instanceof PatternTest
            || $value instanceof TruthTest || $value instanceof UnaryExpression) {
            return $value;
        }

        return $this->atom($value);
    }

    /**
     * Checks a source-free atomic operand, including its marker role.
     * @throws InvalidConstruction When the input has no source-free role
     */
    public function atom(object $value): BlobLiteral|HexIntegerLiteral|IntegerLiteral|NullLiteral|Parameter|RealLiteral|TextLiteral
    {
        Check::input(
            $value instanceof BlobLiteral || $value instanceof HexIntegerLiteral || $value instanceof IntegerLiteral
                || $value instanceof NullLiteral || $value instanceof Parameter || $value instanceof RealLiteral || $value instanceof TextLiteral,
            'A VALUES row needs a scalar operand independent of tables.',
        );

        return $value;
    }
}
