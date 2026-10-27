<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MultipleDelete values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete>
 */
final class MultipleDeleteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput> $input Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm> $form Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $options, ErisGenerator $input, ErisGenerator $form, ?ErisGenerator $where = null, ?ErisGenerator $hints = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(with: $parts[0], options: $parts[1], input: $parts[2], form: $parts[3], where: $parts[4], hints: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $options,
            $input,
            $form,
            $where ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'MultipleDelete/additional/13' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MultipleDeleteGenerator(
            with: Generators::constant(null),
            options: Generators::constant([]),
            input: new Target\DeletionInputGenerator(
                tables: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\JoinedTableGenerator(
                        left: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('t'),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            ),
                            alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            partitions: Generators::constant([]),
                            indexHints: Generators::constant([]),
                            sample: Generators::constant(null),
                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        operator: \Tests\Generator\MySql\Statement\Relation\Join\JoinOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Join]),
                        right: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('u'),
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
                        on: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                            operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                            left: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('x'),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                ),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            right: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('x'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('u'),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                ),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )]),
                        using: Generators::constant([])
                    )])
                ),
                targets: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                ),
                resolutions: Generators::constant(null)
            ),
            form: MultipleDeleteFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::Using]),
            where: Generators::constant(null),
            hints: Generators::constant([])
        )];
    }

}
