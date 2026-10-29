<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GeneratedColumnEntry values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry>
 */
final class GeneratedColumnEntryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry(offset: $parts[0]);
        }, Generators::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\GeneratedColumnEntry' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new GeneratedColumnEntryGenerator(
            offset: Generators::constant(12)
        )];
    }

}
