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
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A checked WHEN operand and the scalar result selected by that branch.
 *
 * @visibility public
 * @example Preserving the decision and result
 *     $when = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1');
 *     $then = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('2');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch($when, $then))->then->decimal // => '2'
 */
final class CaseBranch
{
    use Snapshot;

    /**
     * @param Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression $when The condition or comparison operand
     * @param Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression $then The selected result
     */
    public function __construct(
        public readonly Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression $when,
        public readonly Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression $then,
    ) {
    }
}
