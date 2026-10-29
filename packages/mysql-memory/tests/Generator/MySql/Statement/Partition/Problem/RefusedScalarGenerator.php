<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedScalar values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar>
 */
final class RefusedScalarGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition>|null $condition Generated constructor operand
     * @param ErisGenerator<int>|null $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ?ErisGenerator $condition = null, ?ErisGenerator $offset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar(operand: $parts[0], condition: $parts[1], offset: $parts[2]);
        }, Generators::tuple(
            $operand,
            $condition ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Function),
            $offset ?? Generators::constant(0)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Partition\\Problem\\RefusedScalar' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedScalarGenerator(
            operand: new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                elements: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    ),
                    new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )
                ),
                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            ),
            condition: OperandConditionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Function]),
            offset: Generators::constant(0)
        )];
    }

}
