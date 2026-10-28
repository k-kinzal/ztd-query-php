<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Numeral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>
 */
final class NumeralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<bool>|null $hexadecimal Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation|null>|null $notation Generated constructor operand
     */
    public function __construct(ErisGenerator $text, ?ErisGenerator $hexadecimal = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $notation = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $text,
            $hexadecimal ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($notation): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation $selected): \SqlSemantics\Platform\MySql\Statement\Literal\Numeral => new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral(text: $parts[0], hexadecimal: $parts[1], inputSource: $parts[2], notation: $selected), $notation ?? ($parts[1] ? RadixNotationGenerator::forDigits(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, $parts[0]) : Generators::constant(null)));
        });
    }
    /**
     * Generates fractional, exponent and overflowing integer-position operands.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>
     */
    public static function refusedIntegers(): ErisGenerator
    {
        return new self(Generators::map(static fn (array $parts): string => match ($parts[0]) {
            0 => (string) $parts[1] . '.5',
            1 => (string) $parts[1] . 'e0',
            default => '18446744073709551616' . (string) $parts[1],
        }, \Tests\Generator\Operands::tuple(Generators::elements(0, 1, 2), Generators::choose(1, 99))));
    }

}
