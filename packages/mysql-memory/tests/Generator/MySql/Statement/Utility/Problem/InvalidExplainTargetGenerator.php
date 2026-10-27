<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidExplainTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget>
 */
final class InvalidExplainTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable> $target Generated constructor operand
     */
    public function __construct(ErisGenerator $target)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget(target: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $target
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\InvalidExplainTarget' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidExplainTargetGenerator(
            target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant(''),
                    quote: Generators::constant(null)
                )
            )
        )];
    }

}
