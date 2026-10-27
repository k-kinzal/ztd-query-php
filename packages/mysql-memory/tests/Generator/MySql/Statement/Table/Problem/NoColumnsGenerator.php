<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NoColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns>
 */
final class NoColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns => new \SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\NoColumns' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NoColumnsGenerator()];
    }

}
