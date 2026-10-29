<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Time;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredClock values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock>
 */
final class DeferredClockGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Clock> $clock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $precision Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias|null>|null $alias Generated keyword synonym
     */
    public function __construct(ErisGenerator $clock, ErisGenerator $precision, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind($clock, static function (\SqlSemantics\Platform\MySql\Statement\Call\Clock $selected) use ($precision, $scalarInformation, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock {
                return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock(clock: $parts[0], precision: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3], alias: $parts[4]);
            }, Generators::tuple(
                Generators::constant($selected),
                $precision,
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? \Tests\Generator\MySql\Statement\Call\Keyword\ClockAliasGenerator::forClock($selected)
            ));
        });
    }
}
