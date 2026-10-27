<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NestingConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint>
 */
final class NestingConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec> $window Generated constructor operand
     */
    public function __construct(ErisGenerator $window)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint(window: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $window
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\Window\\NestingConstraint' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NestingConstraintGenerator(
            window: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                base: Generators::constant(null),
                partition: Generators::constant([]),
                order: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                            kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber]),
                            arguments: Generators::constant([]),
                            over: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                                base: Generators::constant(null),
                                partition: Generators::constant([]),
                                order: Generators::constant([]),
                                frame: Generators::constant(null)
                            ),
                            nulls: Generators::constant(null),
                            edge: Generators::constant(null)
                        ),
                        direction: Generators::constant(null)
                    )
                ),
                frame: Generators::constant(null)
            )
        )];
    }

}
