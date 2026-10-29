<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LoadConflict values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict>
 */
final class LoadConflictGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule> $rule Generated constructor operand
     */
    public function __construct(ErisGenerator $rule)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict(rule: $parts[0]);
        }, Generators::tuple(
            $rule
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\LoadConflict' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new LoadConflictGenerator(
            rule: LoadRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule::MultipleFilesWithoutBulk])
        )];
    }

}
