<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ParameterScopeMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch>
 */
final class ParameterScopeMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch => new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\ParameterScopeMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ParameterScopeMismatchGenerator()];
    }

}
