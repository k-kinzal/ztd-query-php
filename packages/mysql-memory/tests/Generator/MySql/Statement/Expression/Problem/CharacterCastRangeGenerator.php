<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CharacterCastRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange>
 */
final class CharacterCastRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange => new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\CharacterCastRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new CharacterCastRangeGenerator()];
    }

}
