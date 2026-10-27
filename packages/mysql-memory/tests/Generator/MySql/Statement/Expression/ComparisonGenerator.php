<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Comparison values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Comparison>
 */
final class ComparisonGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Comparison> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operator, ErisGenerator $left, ErisGenerator $right, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Comparison {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Comparison(operator: $parts[0], left: $parts[1], right: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $operator,
            $left,
            $right,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
