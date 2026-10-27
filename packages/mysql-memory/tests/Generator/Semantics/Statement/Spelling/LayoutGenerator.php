<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Spelling;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Layout values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Spelling\Layout>
 */
final class LayoutGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Spelling\Layout> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Spelling\Spelled>> $tokens Generated constructor operand
     * @param ErisGenerator<string>|null $trail Generated constructor operand
     */
    public function __construct(ErisGenerator $tokens, ?ErisGenerator $trail = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Spelling\Layout {
            return new \SqlSemantics\Statement\Spelling\Layout(tokens: $parts[0], trail: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $tokens,
            $trail ?? Generators::constant('')
        ));
    }
}
