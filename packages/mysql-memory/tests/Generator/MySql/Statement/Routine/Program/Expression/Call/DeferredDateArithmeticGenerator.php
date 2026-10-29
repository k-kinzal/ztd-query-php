<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredDateArithmetic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic>
 */
final class DeferredDateArithmeticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $subtract Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $date Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $quantity Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit> $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias|null>|null $alias Generated matching synonym
     */
    public function __construct(ErisGenerator $subtract, ErisGenerator $date, ErisGenerator $quantity, ErisGenerator $unit, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind($subtract, static function (bool $selected) use ($date, $quantity, $unit, $scalarInformation, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts) use ($selected): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic {
                return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic(subtract: $selected, date: $parts[0], quantity: $parts[1], unit: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], alias: $parts[5]);
            }, Generators::tuple(
                $date,
                $quantity,
                $unit,
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? \Tests\Generator\MySql\Statement\Call\Keyword\DateArithmeticAliasGenerator::forOperation($selected)
            ));
        });
    }
}
