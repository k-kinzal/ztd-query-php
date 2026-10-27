<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes QuantifiedComparison values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison>
 */
final class QuantifiedComparisonGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier> $quantifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $operator, ErisGenerator $quantifier, ErisGenerator $query, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison(operand: $parts[0], operator: $parts[1], quantifier: $parts[2], query: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $operator,
            $quantifier,
            $query,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
