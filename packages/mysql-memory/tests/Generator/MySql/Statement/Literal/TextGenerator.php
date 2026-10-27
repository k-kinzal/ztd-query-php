<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Text values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text>
 */
final class TextGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\Text> */
    use Composition;

    /**
     * @param ErisGenerator<string> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Radix|null>|null $radix Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation|null>|null $notation Generated constructor operand
     */
    public function __construct(ErisGenerator $value, ?ErisGenerator $radix = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $notation = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $value,
            $radix ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($notation): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation $selected): \SqlSemantics\Platform\MySql\Statement\Literal\Text => new \SqlSemantics\Platform\MySql\Statement\Literal\Text(value: $parts[0], radix: $parts[1], inputSource: $parts[2], notation: $selected), $notation ?? ($parts[1] === null ? Generators::constant(null) : RadixNotationGenerator::forDigits($parts[1], $parts[0])));
        });
    }
}
