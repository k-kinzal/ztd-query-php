<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Misuse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse>
 */
final class MisuseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule> $rule Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\ValueRow|null>|null $valueRow Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ?ErisGenerator $name = null, ?ErisGenerator $valueRow = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse(rule: $parts[0], name: $parts[1], valueRow: $parts[2]);
        }, Generators::tuple(
            $rule,
            $name ?? Generators::constant(null),
            $valueRow ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Misuse' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MisuseGenerator(
            rule: MisuseRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::TableFunctionWithoutAlias]),
            name: Generators::constant(null),
            valueRow: Generators::constant(null)
        )];
    }

}
