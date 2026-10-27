<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedScalar values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar>
 */
final class RefusedScalarGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $operand Generated constructor operand
     */
    public function __construct(ErisGenerator $operand)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar(operand: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $operand
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\RefusedScalar' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedScalarGenerator(
            operand: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredSubqueryGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\Set\SetOperationGenerator(
                    left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(
                        rows: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(
                                values: Generators::constant([])
                            )
                        )
                    )]),
                    operator: \Tests\Generator\MySql\Statement\Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                    quantifier: Generators::constant(null),
                    right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('nonexistingvariable'),
                                        quote: Generators::constant(null)
                                    ),
                                    scope: Generators::constant(null),
                                    instance: Generators::constant(null)
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                spellingProfile: Generators::constant(null)
                            )])
                        ),
                        from: Generators::constant(null),
                        where: Generators::constant(null),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        windows: Generators::constant([]),
                        qualify: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        procedure: Generators::constant(null),
                        locking: Generators::constant([]),
                        into: Generators::constant(null),
                        intoPosition: Generators::constant(null),
                        late: Generators::constant(null),
                        hints: Generators::constant([])
                    )])
                )]),
                exists: Generators::constant(false)
            )])
        )];
    }

}
