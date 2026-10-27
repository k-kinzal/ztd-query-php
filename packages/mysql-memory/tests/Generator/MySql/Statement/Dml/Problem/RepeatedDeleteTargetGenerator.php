<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RepeatedDeleteTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget>
 */
final class RepeatedDeleteTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $program Generated constructor operand
     */
    public function __construct(ErisGenerator $targets, ?ErisGenerator $program = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget(targets: $parts[0], program: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $targets,
            $program ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\RepeatedDeleteTarget' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RepeatedDeleteTargetGenerator(
            targets: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('a'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('a'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            program: Generators::constant(null)
        )];
    }

}
