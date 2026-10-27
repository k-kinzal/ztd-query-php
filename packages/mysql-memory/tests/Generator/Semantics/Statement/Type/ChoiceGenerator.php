<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Choice values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\Choice>
 */
final class ChoiceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\Choice> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Type\TypeDescriptor>> $alternatives Generated constructor operand
     */
    public function __construct(ErisGenerator $alternatives)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Type\Choice {
            return new \SqlSemantics\Statement\Type\Choice(alternatives: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $alternatives
        ));
    }
    /**
     * Generates CAST AS REAL before session mode selects FLOAT or DOUBLE.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function realCasts(): ErisGenerator
    {
        $floating = new self(\Tests\Generator\Operands::tuple(
            new \Tests\Generator\MySql\Statement\Type\FloatingGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Float)),
            new \Tests\Generator\MySql\Statement\Type\FloatingGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Double)),
        ));
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 100)), new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new KnownGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer())), Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)));
        $casts = new \Tests\Generator\MySql\Statement\Expression\Conversion\CastGenerator($numbers, new \Tests\Generator\MySql\Statement\Type\CastTargetGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Real)), scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator($floating, Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)));
        return \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($casts);
    }

}
