<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AggregateInOrdering values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering>
 */
final class AggregateInOrderingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering> */
    use Composition;

    /**
     * @param ErisGenerator<int> $position Generated constructor operand
     * @param ErisGenerator<bool>|null $distinct Generated constructor operand
     */
    public function __construct(ErisGenerator $position, ?ErisGenerator $distinct = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering(position: $parts[0], distinct: $parts[1]);
        }, Generators::tuple(
            $position,
            $distinct ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\AggregateInOrdering' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AggregateInOrderingGenerator(
            position: Generators::constant(1),
            distinct: Generators::constant(false)
        )];
    }

}
