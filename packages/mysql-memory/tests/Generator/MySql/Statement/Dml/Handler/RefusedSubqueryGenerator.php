<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSubquery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery>
 */
final class RefusedSubqueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery(offset: $parts[0]);
        }, Generators::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Handler\\RefusedSubquery' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSubqueryGenerator(
            offset: Generators::constant(34)
        )];
    }

}
