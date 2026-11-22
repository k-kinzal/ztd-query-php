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
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|self $operand,
    ) {
    }
}
