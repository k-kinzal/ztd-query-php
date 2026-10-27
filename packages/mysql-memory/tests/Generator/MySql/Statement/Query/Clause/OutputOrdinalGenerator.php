<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Clause;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OutputOrdinal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal>
 */
final class OutputOrdinalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral|\SqlSemantics\Platform\MySql\Statement\Expression\Grouped|\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(expression: $parts[0], scalarInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $expression,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Keeps a generated output position within the projection it references.
     *
     * @param int<1, max> $width The number of output fields
     */
    public static function forWidth(int $width): self
    {
        return new self(new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
            Generators::map(static fn (int $ordinal): string => (string) $ordinal, Generators::choose(1, $width)),
        ));
    }
}
