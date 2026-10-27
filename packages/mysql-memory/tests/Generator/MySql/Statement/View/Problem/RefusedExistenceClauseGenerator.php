<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedExistenceClause values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause>
 */
final class RefusedExistenceClauseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause {
            return new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause(offset: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause>}>
     */
    public static function executionForms(): iterable
    {
        yield 'View\\Problem\\RefusedExistenceClause' => [\SqlSemantics\Contract\GrammarRelease::MySql910, new RefusedExistenceClauseGenerator(
            offset: Generators::constant(23)
        )];
    }

}
