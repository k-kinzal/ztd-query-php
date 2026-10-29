<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WindowConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint>
 */
final class WindowConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec> $window Generated constructor operand
     * @param ErisGenerator<bool> $positional Generated constructor operand
     */
    public function __construct(ErisGenerator $window, ErisGenerator $positional)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint(window: $parts[0], positional: $parts[1]);
        }, Generators::tuple(
            $window,
            $positional
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\WindowConstraint' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new WindowConstraintGenerator(
            window: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                base: Generators::constant(null),
                partition: Generators::constant([]),
                order: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        direction: Generators::constant(null)
                    )
                ),
                frame: Generators::constant(null)
            ),
            positional: Generators::constant(true)
        )];
    }

}
