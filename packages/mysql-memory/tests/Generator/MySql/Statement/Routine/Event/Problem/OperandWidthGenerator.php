<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Event\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OperandWidth values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth>
 */
final class OperandWidthGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns> $problem Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause> $clause Generated constructor operand
     * @param ErisGenerator<bool> $rowValue Generated constructor operand
     */
    public function __construct(ErisGenerator $problem, ErisGenerator $clause, ErisGenerator $rowValue)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(problem: $parts[0], clause: $parts[1], rowValue: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $problem,
            $clause,
            $rowValue
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Event\\Problem\\OperandWidth' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new OperandWidthGenerator(
            problem: new \Tests\Generator\MySql\Statement\Expression\Problem\OperandColumnsGenerator(
                expected: Generators::constant(1),
                actual: Generators::constant(3),
                expression: Generators::constant(null)
            ),
            clause: \Tests\Generator\MySql\Statement\Routine\Event\ClauseGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At]),
            rowValue: Generators::constant(true)
        )];
    }

}
