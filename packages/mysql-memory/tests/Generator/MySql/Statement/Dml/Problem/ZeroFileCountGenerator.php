<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ZeroFileCount values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount>
 */
final class ZeroFileCountGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount(offset: $parts[0]);
        }, Generators::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\ZeroFileCount' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ZeroFileCountGenerator(
            offset: Generators::constant(27)
        )];
    }

}
