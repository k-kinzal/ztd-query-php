<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NonGroupedColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn>
 */
final class NonGroupedColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule> $rule Generated constructor operand
     * @param ErisGenerator<bool> $ordering Generated constructor operand
     * @param ErisGenerator<int> $position Generated constructor operand
     * @param ErisGenerator<string> $column Generated constructor operand
     * @param ErisGenerator<bool>|null $having Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\GrammarRelease>|null $release Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ErisGenerator $ordering, ErisGenerator $position, ErisGenerator $column, ?ErisGenerator $having = null, ?ErisGenerator $release = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn(rule: $parts[0], ordering: $parts[1], position: $parts[2], column: $parts[3], having: $parts[4], release: $parts[5]);
        }, Generators::tuple(
            $rule,
            $ordering,
            $position,
            $column,
            $having ?? Generators::constant(false),
            $release ?? Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\NonGroupedColumn' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NonGroupedColumnGenerator(
            rule: GroupingRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule::NotDetermined]),
            ordering: Generators::constant(false),
            position: Generators::constant(2),
            column: Generators::constant('r.b'),
            having: Generators::constant(false),
            release: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([\SqlSemantics\Contract\GrammarRelease::MySql847])
        )];
    }

}
