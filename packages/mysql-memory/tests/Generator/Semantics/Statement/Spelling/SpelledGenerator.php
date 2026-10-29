<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Spelling;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Spelled values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Spelling\Spelled>
 */
final class SpelledGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Spelling\Spelled> */
    use Composition;

    /**
     * @param ErisGenerator<string> $gap Generated constructor operand
     * @param ErisGenerator<string> $text Generated constructor operand
     */
    public function __construct(ErisGenerator $gap, ErisGenerator $text)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Spelling\Spelled {
            return new \SqlSemantics\Statement\Spelling\Spelled(gap: $parts[0], text: $parts[1]);
        }, Generators::tuple(
            $gap,
            $text
        ));
    }
}
