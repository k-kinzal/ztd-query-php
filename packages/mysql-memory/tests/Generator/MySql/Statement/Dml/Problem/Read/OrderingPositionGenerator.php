<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Read;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OrderingPosition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition>
 */
final class OrderingPositionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral> $position Generated constructor operand
     */
    public function __construct(ErisGenerator $position)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition(position: $parts[0]);
        }, Generators::tuple(
            $position
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Read\\OrderingPosition' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new OrderingPositionGenerator(
            position: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
            )
        )];
    }

}
