<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column\Value;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CurrentTimestamp values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp>
 */
final class CurrentTimestampGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $precision Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $precision = null, ?ErisGenerator $parentheses = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp(precision: $parts[0], parentheses: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $precision ?? Generators::constant(null),
            $parentheses ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
