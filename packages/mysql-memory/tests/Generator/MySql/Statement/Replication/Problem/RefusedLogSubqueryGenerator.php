<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedLogSubquery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery>
 */
final class RefusedLogSubqueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery(offset: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Replication\\Problem\\RefusedLogSubquery' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedLogSubqueryGenerator(
            offset: Generators::constant(32)
        )];
    }

}
