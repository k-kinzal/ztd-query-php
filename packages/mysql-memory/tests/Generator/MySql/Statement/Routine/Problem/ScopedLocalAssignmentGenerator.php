<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ScopedLocalAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment>
 */
final class ScopedLocalAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<int|null>|null $offset Generated constructor operand
     * @param ErisGenerator<int|null>|null $end Generated constructor operand
     */
    public function __construct(?ErisGenerator $offset = null, ?ErisGenerator $end = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment(offset: $parts[0], end: $parts[1]);
        }, Generators::tuple(
            $offset ?? Generators::constant(null),
            $end ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\ScopedLocalAssignment' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ScopedLocalAssignmentGenerator(
            offset: Generators::constant(17),
            end: Generators::constant(null)
        )];
    }

}
