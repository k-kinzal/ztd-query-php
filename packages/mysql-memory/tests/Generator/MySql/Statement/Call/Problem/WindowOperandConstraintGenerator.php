<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WindowOperandConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint>
 */
final class WindowOperandConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec> $window Generated constructor operand
     */
    public function __construct(ErisGenerator $window)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint(window: $parts[0]);
        }, Generators::tuple(
            $window
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\WindowOperandConstraint' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new WindowOperandConstraintGenerator(
            window: new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredWindowSpecGenerator(
                base: Generators::constant(null),
                partition: Generators::constant([]),
                order: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
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
                        direction: Generators::constant(null)
                    )
                ),
                frame: Generators::constant(null)
            )
        )];
    }

}
