<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Temporal;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;

/**
 * Composes date arithmetic operands and interval keywords through Eris.
 *
 * @implements ErisGenerator<DateArithmetic>
 */
final class DateArithmeticGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<DateArithmetic>
     */
    private ErisGenerator $delegate;

    /**
     * Generates bounded quantities against a fixed datetime, keeping every unit.
     */
    public function __construct()
    {
        $this->delegate = self::fromOperands(
            Generators::bool(),
            new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::constant('2024-06-15 12:34:56.123456')),
            new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::map(static fn (int $number): string => (string) $number, Generators::int())),
            new \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator()
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<DateArithmetic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DateArithmetic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool> $subtract The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $date The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $quantity The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit> $unit The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias|null>|null $alias The generated matching synonym
     * @return ErisGenerator<DateArithmetic>
     */
    public static function fromOperands(ErisGenerator $subtract, ErisGenerator $date, ErisGenerator $quantity, ErisGenerator $unit, ?ErisGenerator $alias = null): ErisGenerator
    {
        return \Tests\Generator\Operands::bind($subtract, static function (bool $selected) use ($date, $quantity, $unit, $alias): ErisGenerator {
            return Generators::map(static fn (array $parts): DateArithmetic => new DateArithmetic(subtract: $selected, date: $parts[0], quantity: $parts[1], unit: $parts[2], alias: $parts[3]), \Tests\Generator\Operands::tuple(
                $date,
                $quantity,
                $unit,
                $alias ?? \Tests\Generator\MySql\Statement\Call\Keyword\DateArithmeticAliasGenerator::forOperation($selected)
            ));
        });
    }

}
