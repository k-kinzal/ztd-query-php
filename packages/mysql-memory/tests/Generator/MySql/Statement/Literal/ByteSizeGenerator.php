<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ByteSize values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\ByteSize>
 */
final class ByteSizeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\ByteSize> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null> $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $word Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $number, ?ErisGenerator $word = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize {
            return new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(number: $parts[0], word: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $number,
            $word ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
