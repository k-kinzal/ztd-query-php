<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedConnectionExplain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain>
 */
final class RefusedConnectionExplainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $analyze Generated constructor operand
     */
    public function __construct(ErisGenerator $analyze)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain(analyze: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $analyze
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\RefusedConnectionExplain' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedConnectionExplainGenerator(
            analyze: Generators::constant(true)
        )];
    }

}
