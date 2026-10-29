<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LanguageBodyMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch>
 */
final class LanguageBodyMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<int|null>|null $offset Generated constructor operand
     */
    public function __construct(?ErisGenerator $offset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch(offset: $parts[0]);
        }, Generators::tuple(
            $offset ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\LanguageBodyMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new LanguageBodyMismatchGenerator(
            offset: Generators::constant(null)
        )];
    }

}
