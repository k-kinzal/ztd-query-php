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
     */
    public function __construct(ErisGenerator $text, ?ErisGenerator $hexadecimal = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Literal\Numeral {
            return new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral(text: $parts[0], hexadecimal: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $text,
            $hexadecimal ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
