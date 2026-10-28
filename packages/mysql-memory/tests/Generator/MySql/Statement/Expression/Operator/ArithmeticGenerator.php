<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Operator;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Arithmetic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic>
 */
final class ArithmeticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<int>|null $divisionPrecision The generated calculation precision for bound divisions
     * @param ErisGenerator<bool>|null $modWord The generated written alternative
     */
    public function __construct(ErisGenerator $operator, ErisGenerator $left, ErisGenerator $right, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $modWord = null, ?ErisGenerator $divisionPrecision = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(operator: $parts[0], left: $parts[1], right: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], modWord: $parts[5] && $parts[0] === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Modulo, divisionPrecision: $parts[3] !== null && $parts[0] === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide ? $parts[6] : null);
        }, \Tests\Generator\Operands::tuple(
            $operator,
            $left,
            $right,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $modWord ?? Generators::bool(),
            $divisionPrecision ?? Generators::choose(0, 30)
        ));
    }
}
