<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StorageProblem values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem>
 */
final class StorageProblemGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule> $rule Generated constructor operand
     * @param ErisGenerator<string> $option Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ErisGenerator $option)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem(rule: $parts[0], option: $parts[1]);
        }, Generators::tuple(
            $rule,
            $option
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\StorageProblem' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new StorageProblemGenerator(
            rule: StorageRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule::RepeatedOption]),
            option: Generators::constant('FILE_BLOCK_SIZE')
        )];
    }

}
