<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DollarQuotedText values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText>
 */
final class DollarQuotedTextGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<string>|null $tag Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $text, ?ErisGenerator $tag = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText(text: $parts[0], tag: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $text,
            $tag ?? Generators::constant(''),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
