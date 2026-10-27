<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownCharset values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset>
 */
final class UnknownCharsetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\UnknownCharset' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownCharsetGenerator(
            name: Generators::constant('absent')
        )];
    }

}
