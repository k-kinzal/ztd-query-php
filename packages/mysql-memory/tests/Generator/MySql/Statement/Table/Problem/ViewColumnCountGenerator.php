<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ViewColumnCount values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount>
 */
final class ViewColumnCountGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount> */
    use Composition;

    /**
     * @param ErisGenerator<int> $listed Generated constructor operand
     * @param ErisGenerator<int> $returned Generated constructor operand
     */
    public function __construct(ErisGenerator $listed, ErisGenerator $returned)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount(listed: $parts[0], returned: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $listed,
            $returned
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\ViewColumnCount' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ViewColumnCountGenerator(
            listed: Generators::constant(2),
            returned: Generators::constant(1)
        )];
    }

}
