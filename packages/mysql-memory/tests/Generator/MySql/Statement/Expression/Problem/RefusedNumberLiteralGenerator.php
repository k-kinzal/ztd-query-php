<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedNumberLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral>
 */
final class RefusedNumberLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     */
    public function __construct(ErisGenerator $text)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral(text: $parts[0]);
        }, Generators::tuple(
            $text
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\RefusedNumberLiteral' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedNumberLiteralGenerator(
            text: Generators::constant('2e381')
        )];
    }

}
