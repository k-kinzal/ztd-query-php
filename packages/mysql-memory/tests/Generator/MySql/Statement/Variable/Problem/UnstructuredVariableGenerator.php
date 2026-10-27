<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnstructuredVariable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable>
 */
final class UnstructuredVariableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Variable\\Problem\\UnstructuredVariable' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new UnstructuredVariableGenerator(
            name: Generators::constant('sort_buffer_size')
        )];
    }

}
