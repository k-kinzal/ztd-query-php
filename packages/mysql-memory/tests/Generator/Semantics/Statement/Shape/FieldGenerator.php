<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Field values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\Field>
 */
final class FieldGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\Field> */
    use Composition;

    /**
     * @param ErisGenerator<int> $position Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\OutputSlot> $slot Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Column\Resolution|null>|null $resolution Generated constructor operand
     */
    public function __construct(ErisGenerator $position, ErisGenerator $slot, ?ErisGenerator $expression = null, ?ErisGenerator $resolution = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\Field {
            return new \SqlSemantics\Statement\Shape\Field(position: $parts[0], slot: $parts[1], expression: $parts[2], resolution: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $position,
            $slot,
            $expression ?? Generators::constant(null),
            $resolution ?? Generators::constant(null)
        ));
    }

    /**
     * Generates a named output from the expression's own type and resolution.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expressions Generated locally bound scalar occurrences
     * @return ErisGenerator<\SqlSemantics\Statement\Shape\Field>
     */
    public static function namedExpression(ErisGenerator $expressions): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\Field {
            [$expression, $name] = $parts;
            $fact = $expression->scalarInformation();
            return new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot($name, $fact->type, $fact->nullability), $expression, $fact->resolution);
        }, \Tests\Generator\Operands::tuple($expressions, new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()));
    }
}
