<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One Boolean decision and its result in the int4 domain.
 *
 * @visibility public
 * @example Choosing the given integer when the condition is true
 *     $then = new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('7');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm(new \SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral(true), $then))->result->decimal // => '7'
 */
final class IntegerCaseArm
{
    use Snapshot;

    /**
     * Holds the checked condition and its corresponding scalar value.
     */
    public function __construct(
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest $condition,
        public readonly Arithmetic|ColumnReference|IntegerCase|IntegerLiteral|NullLiteral $result,
    ) {
    }
}
