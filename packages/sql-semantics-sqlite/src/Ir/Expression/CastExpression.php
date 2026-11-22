<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A native conversion of one resolved SQLite scalar operand.
 *
 * @visibility public
 * @example Selecting the TEXT conversion
 *     $number = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('7');
 *     $target = \SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget::Text;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression($target, $number))->target->value // => 'TEXT'
 */
final class CastExpression
{
    use Snapshot;

    /**
     * @param CastTarget $target The effective SQLite conversion
     * @param Arithmetic|BetweenTest|BlobLiteral|CaseExpression|self|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression $operand The evaluated scalar
     */
    public function __construct(
        public readonly CastTarget $target,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|self|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression $operand,
    ) {
    }
}
