<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnpartitionedTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable>
 */
final class UnpartitionedTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable => new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\UnpartitionedTable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnpartitionedTableGenerator()];
    }

}
