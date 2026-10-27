<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NotSupportedYet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet>
 */
final class NotSupportedYetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet> */
    use Composition;

    /**
     * @param ErisGenerator<string> $feature Generated constructor operand
     */
    public function __construct(ErisGenerator $feature)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet(feature: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $feature
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\NotSupportedYet' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NotSupportedYetGenerator(
            feature: Generators::constant('LIMIT & IN/ALL/ANY/SOME subquery')
        )];
    }

}
