<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedEvaluation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation>
 */
final class RefusedEvaluationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation> $evaluation Generated constructor operand
     */
    public function __construct(ErisGenerator $evaluation)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation(evaluation: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $evaluation
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\RefusedEvaluation' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedEvaluationGenerator(
            evaluation: new \Tests\Generator\MySql\Statement\Routine\Program\DeferredEvaluationGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Routine\Program\DeferredProjectionGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: \Tests\Generator\Operands::tuple(
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: \Tests\Generator\Operands::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )])
                                ),
                                from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $suffix): string => 'missing' . $suffix, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        schema: Generators::constant(null),
                                        catalog: Generators::constant(null)
                                    ),
                                    alias: Generators::constant(null),
                                    partitions: Generators::constant([]),
                                    indexHints: Generators::constant([]),
                                    sample: Generators::constant(null),
                                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
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
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )
                )
            )
        )];
    }

}
