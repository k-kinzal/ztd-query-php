<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TimeZoneConversion values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion>
 */
final class TimeZoneConversionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone> $expression Generated constructor operand
     * @param ErisGenerator<bool> $operand Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ErisGenerator $operand)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion(expression: $parts[0], operand: $parts[1]);
        }, Generators::tuple(
            $expression,
            $operand
        ));
    }
    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression/Problem/TimeZoneConversion' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(
            new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTimeZoneGenerator(
                new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 100))),
                new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements(['+00:00', '-03:00'])),
            ),
            Generators::constant(true),
        )];
    }

}
