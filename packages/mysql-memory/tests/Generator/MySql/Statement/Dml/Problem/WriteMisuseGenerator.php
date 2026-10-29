<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WriteMisuse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse>
 */
final class WriteMisuseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule> $rule Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource|null>|null $target Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ?ErisGenerator $table = null, ?ErisGenerator $target = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse(rule: $parts[0], table: $parts[1], target: $parts[2]);
        }, Generators::tuple(
            $rule,
            $table ?? Generators::constant(null),
            $target ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\WriteMisuse' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new WriteMisuseGenerator(
            rule: WriteRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::DefaultOutsideInsert]),
            table: Generators::constant(null),
            target: Generators::constant(null)
        )];
    }

}
