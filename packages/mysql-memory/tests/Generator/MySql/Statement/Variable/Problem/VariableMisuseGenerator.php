<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes VariableMisuse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse>
 */
final class VariableMisuseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule> $rule Generated constructor operand
     * @param ErisGenerator<string> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse(rule: $parts[0], name: $parts[1]);
        }, Generators::tuple(
            $rule,
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Variable\\Problem\\VariableMisuse' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new VariableMisuseGenerator(
            rule: VariableRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule::ReadOnly]),
            name: Generators::constant('version')
        )];
    }

}
