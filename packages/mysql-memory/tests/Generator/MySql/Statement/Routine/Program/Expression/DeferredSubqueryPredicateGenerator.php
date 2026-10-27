<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredSubqueryPredicate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate>
 */
final class DeferredSubqueryPredicateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator|null>|null $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier|null>|null $quantifier Generated constructor operand
     * @param ErisGenerator<bool>|null $negated Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $query, ?ErisGenerator $operator = null, ?ErisGenerator $quantifier = null, ?ErisGenerator $negated = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate(operand: $parts[0], query: $parts[1], operator: $parts[2], quantifier: $parts[3], negated: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $query,
            $operator ?? Generators::constant(null),
            $quantifier ?? Generators::constant(null),
            $negated ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
