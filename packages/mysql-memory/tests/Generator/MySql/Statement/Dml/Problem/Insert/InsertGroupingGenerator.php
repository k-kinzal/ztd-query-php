<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InsertGrouping values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping>
 */
final class InsertGroupingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate|\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction> $function Generated constructor operand
     */
    public function __construct(ErisGenerator $function)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping(function: $parts[0]);
        }, Generators::tuple(
            $function
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Insert\\InsertGrouping' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InsertGroupingGenerator(
            function: new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum]),
                arguments: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )
                ),
                distinct: Generators::constant(false),
                all: Generators::constant(false),
                over: Generators::constant(null)
            )
        )];
    }

}
