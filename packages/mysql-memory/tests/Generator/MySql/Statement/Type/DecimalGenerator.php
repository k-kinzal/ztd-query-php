<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Decimal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Decimal>
 */
final class DecimalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Decimal> */
    use Composition;

    /**
     * @param ErisGenerator<string|null>|null $precision Generated constructor operand
     * @param ErisGenerator<string|null>|null $scale Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier>>|null $modifiers Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\DecimalWord>|null $word Generated type keyword
     */
    public function __construct(?ErisGenerator $precision = null, ?ErisGenerator $scale = null, ?ErisGenerator $modifiers = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $word = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Decimal {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Decimal(precision: $parts[0], scale: $parts[1], modifiers: $parts[2], inputSource: $parts[3], word: $parts[4]);
        }, Generators::tuple(
            $precision ?? Generators::constant(null),
            $scale ?? Generators::constant(null),
            $modifiers ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null),
            $word ?? new Keyword\DecimalWordGenerator()
        ));
    }
}
