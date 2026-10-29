<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SelectOptionConflict values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict>
 */
final class SelectOptionConflictGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $first Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $second Generated constructor operand
     */
    public function __construct(ErisGenerator $first, ErisGenerator $second)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict(first: $parts[0], second: $parts[1]);
        }, Generators::tuple(
            $first,
            $second
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\SelectOptionConflict' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SelectOptionConflictGenerator(
            first: \Tests\Generator\MySql\Statement\Query\SelectOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All]),
            second: \Tests\Generator\MySql\Statement\Query\SelectOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct])
        )];
    }

}
