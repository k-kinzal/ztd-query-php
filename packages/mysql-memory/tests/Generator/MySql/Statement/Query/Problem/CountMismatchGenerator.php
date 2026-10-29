<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CountMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch>
 */
final class CountMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList> $list Generated constructor operand
     * @param ErisGenerator<int> $expected Generated constructor operand
     * @param ErisGenerator<int> $actual Generated constructor operand
     * @param ErisGenerator<int>|null $row Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\ValueRow|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow|null>|null $valueRow Generated constructor operand
     */
    public function __construct(ErisGenerator $list, ErisGenerator $expected, ErisGenerator $actual, ?ErisGenerator $row = null, ?ErisGenerator $valueRow = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch(list: $parts[0], expected: $parts[1], actual: $parts[2], row: $parts[3], valueRow: $parts[4]);
        }, Generators::tuple(
            $list,
            $expected,
            $actual,
            $row ?? Generators::constant(1),
            $valueRow ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\CountMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new CountMismatchGenerator(
            list: CountedListGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList::SetOperands]),
            expected: Generators::constant(1),
            actual: Generators::constant(0),
            row: Generators::constant(1),
            valueRow: Generators::constant(null)
        )];
    }

}
