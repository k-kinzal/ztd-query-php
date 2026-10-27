<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedExplainInto values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto>
 */
final class RefusedExplainIntoGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<bool>|null $analyze Generated constructor operand
     */
    public function __construct(?ErisGenerator $format = null, ?ErisGenerator $analyze = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto(format: $parts[0], analyze: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $format ?? Generators::constant(null),
            $analyze ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\RefusedExplainInto' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedExplainIntoGenerator(
            format: Generators::constant(null),
            analyze: Generators::constant(false)
        )];
    }

}
