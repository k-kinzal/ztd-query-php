<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedNamesAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment>
 */
final class RefusedNamesAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<int> $end Generated constructor operand
     * @param ErisGenerator<bool>|null $shadowed Generated constructor operand
     */
    public function __construct(ErisGenerator $offset, ErisGenerator $end, ?ErisGenerator $shadowed = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment(offset: $parts[0], end: $parts[1], shadowed: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $offset,
            $end,
            $shadowed ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\RefusedNamesAssignment' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedNamesAssignmentGenerator(
            offset: Generators::constant(10),
            end: Generators::constant(13),
            shadowed: Generators::constant(false)
        )];
    }

}
