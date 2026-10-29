<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SpatialArithmetic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic>
 */
final class SpatialArithmeticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator|\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator> $operator Generated constructor operand
     */
    public function __construct(ErisGenerator $operator)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic(operator: $parts[0]);
        }, Generators::tuple(
            $operator
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\SpatialArithmetic' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SpatialArithmeticGenerator(
            operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::IntegerDivide])
        )];
    }

}
