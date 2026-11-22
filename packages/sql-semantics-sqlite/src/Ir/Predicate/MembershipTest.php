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
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A scalar membership test with an owned operand list.
 *
 * @visibility public
 * @example Representing an empty list
 *     $test = new \SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest(new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral(), []);
 *     $test->items // => []
 */
final class MembershipTest
{
    use Snapshot;

    /**
     * @var list<MembershipItem> The candidate expressions in their written order
     */
    public readonly array $items;

    /**
     * Holds a scalar and its checked list of candidates.
     * @param list<MembershipItem> $items The ordered list, which can be empty
     */
    public function __construct(
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|self|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $operand,
        array $items,
        public readonly bool $negated = false,
    ) {
        Check::input(count($items) <= 8190, 'A membership list exceeds the expanded expression envelope.');
        $this->items = Check::listOf($items, MembershipItem::class, 'Expected an ordered list of scalar membership items.');
    }
}
