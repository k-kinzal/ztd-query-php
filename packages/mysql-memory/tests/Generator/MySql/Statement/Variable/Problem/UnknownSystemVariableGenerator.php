<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownSystemVariable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable>
 */
final class UnknownSystemVariableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<bool>|null $assigned Generated constructor operand
     * @param ErisGenerator<bool>|null $structured Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $assigned = null, ?ErisGenerator $structured = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable(name: $parts[0], assigned: $parts[1], structured: $parts[2]);
        }, Generators::tuple(
            $name,
            $assigned ?? Generators::constant(false),
            $structured ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Variable\\Problem\\UnknownSystemVariable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownSystemVariableGenerator(
            name: Generators::constant('component.variable_name'),
            assigned: Generators::constant(true),
            structured: Generators::constant(true)
        )];
    }

}
