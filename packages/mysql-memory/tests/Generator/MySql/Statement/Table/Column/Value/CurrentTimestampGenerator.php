<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column\Value;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CurrentTimestamp values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp>
 */
final class CurrentTimestampGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $precision Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias|null>|null $alias Generated timestamp keyword
     */
    public function __construct(?ErisGenerator $precision = null, ?ErisGenerator $parentheses = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind($parentheses ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), static function (\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $words) use ($precision, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp {
                return new \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp(precision: $parts[0], parentheses: $parts[1], inputSource: $parts[2], alias: $parts[3]);
            }, Generators::tuple(
                $precision ?? Generators::constant(null),
                Generators::constant($words),
                $inputSource ?? Generators::constant(null),
                $alias ?? ($words === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written ? \Tests\Generator\MySql\Statement\Call\Keyword\ClockAliasGenerator::forClock(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now) : \Tests\Generator\MySql\Statement\Call\Keyword\ClockAliasGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::CurrentTimestamp, \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::LocalTime, \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::LocalTimestamp]))
            ));
        });
    }
}
