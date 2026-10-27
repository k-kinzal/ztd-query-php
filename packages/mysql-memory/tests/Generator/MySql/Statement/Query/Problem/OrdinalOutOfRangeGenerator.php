<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OrdinalOutOfRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange>
 */
final class OrdinalOutOfRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange> */
    use Composition;

    /**
     * @param ErisGenerator<int> $position Generated constructor operand
     * @param ErisGenerator<int> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal|null>|null $ordinal Generated constructor operand
     * @param ErisGenerator<bool>|null $grouped Generated constructor operand
     */
    public function __construct(ErisGenerator $position, ErisGenerator $items, ?ErisGenerator $ordinal = null, ?ErisGenerator $grouped = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange(position: $parts[0], items: $parts[1], ordinal: $parts[2], grouped: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $position,
            $items,
            $ordinal ?? Generators::constant(null),
            $grouped ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\OrdinalOutOfRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new OrdinalOutOfRangeGenerator(
            position: Generators::constant(2),
            items: Generators::constant(1),
            ordinal: Generators::constant(null),
            grouped: Generators::constant(false)
        )];
    }

}
