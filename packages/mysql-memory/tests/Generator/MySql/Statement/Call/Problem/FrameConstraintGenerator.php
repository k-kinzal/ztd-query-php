<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FrameConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint>
 */
final class FrameConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec> $window Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition> $condition Generated constructor operand
     */
    public function __construct(ErisGenerator $window, ErisGenerator $condition)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint(window: $parts[0], condition: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $window,
            $condition
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\FrameConstraint' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new FrameConstraintGenerator(
            window: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                base: Generators::constant(null),
                partition: Generators::constant([]),
                order: Generators::constant([]),
                frame: new \Tests\Generator\MySql\Statement\Call\Window\FrameGenerator(
                    unit: \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows]),
                    start: new \Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator(
                        kind: \Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Preceding]),
                        offset: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::constant('1.5')
                        ),
                        unit: Generators::constant(null)
                    ),
                    end: Generators::constant(null),
                    exclusion: Generators::constant(null)
                )
            ),
            condition: FrameConditionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::Offset])
        )];
    }

}
