<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NumberLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral>
 */
final class NumberLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $text, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral {
            return new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral(text: $parts[0], scalarInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $text,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates all numeric categories while shrinking the form and magnitude.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral>
     */
    public static function numbers(): ErisGenerator
    {
        return new self(Generators::map(static function (array $parts): string {
            [$form, $value] = $parts;
            return match ($form) {
                \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Integer => (string) $value,
                \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Decimal => $value . '.5',
                \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Float => $value . 'e1',
            };
        }, Generators::tuple(new NumberFormGenerator(), Generators::choose(0, 32))));
    }

}
