<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AggregateNestingConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint>
 */
final class AggregateNestingConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate|\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction> $call Generated constructor operand
     */
    public function __construct(ErisGenerator $call)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint(call: $parts[0]);
        }, Generators::tuple(
            $call
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\Aggregate\\AggregateNestingConstraint' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AggregateNestingConstraintGenerator(
            call: new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count]),
                arguments: Generators::constant([]),
                distinct: Generators::constant(false),
                all: Generators::constant(false),
                over: Generators::constant(null)
            )
        )];
    }

}
