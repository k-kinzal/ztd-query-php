<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RestrictedSelectOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption>
 */
final class RestrictedSelectOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $option Generated constructor operand
     */
    public function __construct(ErisGenerator $option)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption(option: $parts[0]);
        }, Generators::tuple(
            $option
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Option\\RestrictedSelectOption' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RestrictedSelectOptionGenerator(
            option: \Tests\Generator\MySql\Statement\Query\SelectOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority])
        )];
    }

}
