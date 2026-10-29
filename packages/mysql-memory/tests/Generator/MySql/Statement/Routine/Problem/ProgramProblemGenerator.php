<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ProgramProblem values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem>
 */
final class ProgramProblemGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule> $rule Generated constructor operand
     * @param ErisGenerator<string|null>|null $subject Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ?ErisGenerator $subject = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(rule: $parts[0], subject: $parts[1]);
        }, Generators::tuple(
            $rule,
            $subject ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\ProgramProblem' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ProgramProblemGenerator(
            rule: ProgramRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::EventRecursion]),
            subject: Generators::constant(null)
        )];
    }

}
