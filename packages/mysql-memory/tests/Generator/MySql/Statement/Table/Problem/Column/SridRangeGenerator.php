<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SridRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange>
 */
final class SridRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $srid Generated constructor operand
     */
    public function __construct(ErisGenerator $srid)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange(srid: $parts[0]);
        }, Generators::tuple(
            $srid
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Column\\SridRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SridRangeGenerator(
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('4294967296'),
                hexadecimal: Generators::constant(false)
            )
        )];
    }

}
