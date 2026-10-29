<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredArithmetic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic>
 */
final class DeferredArithmeticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $modWord The generated written alternative
     */
    public function __construct(ErisGenerator $operator, ErisGenerator $left, ErisGenerator $right, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $modWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(operator: $parts[0], left: $parts[1], right: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], modWord: $parts[5] && $parts[0] === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Modulo);
        }, Generators::tuple(
            $operator,
            $left,
            $right,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $modWord ?? Generators::bool()
        ));
    }
}
