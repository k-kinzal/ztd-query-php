<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UtilityMisuse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse>
 */
final class UtilityMisuseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule> $rule Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node|null>|null $subject Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ?ErisGenerator $subject = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse(rule: $parts[0], subject: $parts[1]);
        }, Generators::tuple(
            $rule,
            $subject ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\UtilityMisuse' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UtilityMisuseGenerator(
            rule: UtilityRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly]),
            subject: Generators::constant(null)
        )];
    }

}
