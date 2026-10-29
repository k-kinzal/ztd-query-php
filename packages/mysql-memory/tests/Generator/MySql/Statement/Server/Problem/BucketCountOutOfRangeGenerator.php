<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BucketCountOutOfRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange>
 */
final class BucketCountOutOfRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $buckets Generated constructor operand
     */
    public function __construct(ErisGenerator $buckets)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange(buckets: $parts[0]);
        }, Generators::tuple(
            $buckets
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\BucketCountOutOfRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new BucketCountOutOfRangeGenerator(
            buckets: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('2000'),
                hexadecimal: Generators::constant(false)
            )
        )];
    }

}
