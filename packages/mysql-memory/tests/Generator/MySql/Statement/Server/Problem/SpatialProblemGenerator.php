<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SpatialProblem values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem>
 */
final class SpatialProblemGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule> $rule Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind|null>|null $attribute Generated constructor operand
     * @param ErisGenerator<string|null>|null $value Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ?ErisGenerator $attribute = null, ?ErisGenerator $value = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(rule: $parts[0], attribute: $parts[1], value: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $rule,
            $attribute ?? Generators::constant(null),
            $value ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\SpatialProblem' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SpatialProblemGenerator(
            rule: SpatialRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::MissingAttribute]),
            attribute: \Tests\Generator\MySql\Statement\Server\Spatial\SpatialAttributeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition]),
            value: Generators::constant(null)
        )];
    }

}
