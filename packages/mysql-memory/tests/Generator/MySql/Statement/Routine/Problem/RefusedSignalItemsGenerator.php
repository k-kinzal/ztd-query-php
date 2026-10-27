<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSignalItems values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems>
 */
final class RefusedSignalItemsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal> $prefix Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem> $problem Generated constructor operand
     */
    public function __construct(ErisGenerator $prefix, ErisGenerator $problem)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems(prefix: $parts[0], problem: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $prefix,
            $problem
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\RefusedSignalItems' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSignalItemsGenerator(
            prefix: new \Tests\Generator\MySql\Statement\Routine\Condition\ResignalGenerator(
                condition: Generators::constant(null),
                items: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Routine\Condition\SignalItemGenerator(
                        name: \Tests\Generator\MySql\Statement\Routine\Condition\ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MessageText]),
                        value: new \Tests\Generator\MySql\Statement\LiteralExpressionGenerator([\Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                            segments: \Tests\Generator\Operands::tuple(
                                Generators::constant('a')
                            ),
                            introducer: Generators::constant(null),
                            national: Generators::constant(false),
                            collation: Generators::constant(null)
                        )])
                    )
                )
            ),
            problem: new \Tests\Generator\MySql\Statement\Routine\Condition\SignalItemGenerator(
                name: \Tests\Generator\MySql\Statement\Routine\Condition\ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MessageText]),
                value: new \Tests\Generator\MySql\Statement\LiteralExpressionGenerator([\Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                    segments: \Tests\Generator\Operands::tuple(
                        Generators::constant('b')
                    ),
                    introducer: Generators::constant(null),
                    national: Generators::constant(false),
                    collation: Generators::constant(null)
                )])
            )
        )];
    }

}
