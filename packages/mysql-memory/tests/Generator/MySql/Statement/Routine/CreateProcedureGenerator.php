<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CreateProcedure values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure>
 */
final class CreateProcedureGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure> */
    use Composition;

    /**
     * Generates complete CreateProcedure values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                $forms[] = self::operands(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()), new ParameterListGenerator(Generators::constant([])), new \Tests\Generator\MySql\Statement\Server\Transaction\TransactionStatementGenerator($release));
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No CreateProcedure forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure) {
                throw new LogicException('Publishing CreateProcedure must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList> $parameters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node> $body Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic>>|null $characteristics Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource|null>|null $definitionSource Generated input boundaries
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $parameters, ErisGenerator $body, ?ErisGenerator $characteristics = null, ?ErisGenerator $definer = null, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $definitionSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(name: $parts[0], parameters: $parts[1], body: $parts[2], characteristics: $parts[3], definer: $parts[4], ifNotExists: $parts[5], statementInformation: $parts[6], inputSource: $parts[7], definitionSource: $parts[8]);
        }, Generators::tuple($name, $parameters, $body, $characteristics ?? Generators::constant([]), $definer ?? Generators::constant(null), $ifNotExists ?? Generators::constant(false), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $definitionSource ?? Generators::oneOf(Generators::constant(null), new Definition\DefinitionSourceGenerator())));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure>>
     */
    public static function forms(): iterable
    {
        yield 'CreateProcedure/154' => CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new ExternalBodyGenerator(
                code: new DollarQuotedTextGenerator(
                    text: Generators::constant('text'),
                    tag: Generators::constant('')
                )
            )]),
            characteristics: Generators::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                    external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('javascript'),
                        quote: Generators::constant(null)
                    )
                )])
            ),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateProcedure/155' => CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new ExternalBodyGenerator(
                code: new DollarQuotedTextGenerator(
                    text: Generators::constant('text'),
                    tag: Generators::constant('')
                )
            )]),
            characteristics: Generators::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\DeterminismGenerator(
                    deterministic: Generators::constant(true)
                )]),
                new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                    external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('javascript'),
                        quote: Generators::constant(null)
                    )
                )])
            ),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'CreateProcedure/additional/12' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Selection\DeferredQueryGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(
                    rows: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(
                            values: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator()
                            )
                        )
                    )
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/14' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredInsertQueryGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('d'),
                                quote: Generators::constant(null)
                            ),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator(
                    selection: new Program\DeferredSelectionGenerator(
                        items: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                                expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )]),
                                alias: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                            )])
                        ),
                        from: Generators::constant(null),
                        where: Generators::constant(null),
                        options: Generators::constant([]),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        windows: Generators::constant([]),
                        procedure: Generators::constant(null),
                        qualify: Generators::constant(null),
                        into: Generators::constant(null)
                    )
                )]),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/15' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredInsertRowsGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('d'),
                                quote: Generators::constant(null)
                            ),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                rows: Generators::tuple(
                    new Program\Write\DeferredInsertedRowGenerator(
                        values: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )])
                        )
                    )
                ),
                alias: Generators::constant(null),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/16' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredDeleteGenerator(
                with: new \Tests\Generator\MySql\Statement\Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
                        )
                    )
                ),
                options: Generators::constant([]),
                table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                ),
                where: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                hints: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/18' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        mode: Generators::constant(null)
                    ),
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('b'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\CharacterGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\CharacterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::VarChar]),
                            length: Generators::constant('3'),
                            national: Generators::constant(false),
                            charset: new \Tests\Generator\MySql\Statement\Type\CharsetAttributeGenerator(
                                form: \Tests\Generator\MySql\Statement\Type\Kind\CharsetFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet]),
                                charset: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('utf8mb4'),
                                    quote: Generators::constant(null)
                                ),
                                mark: \Tests\Generator\MySql\Statement\Type\Kind\BinaryMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Absent])
                            )
                        )]),
                        collation: new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('utf8mb4_bin'),
                                quote: Generators::constant(null)
                            )
                        ),
                        mode: ParameterModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode::Out])
                    )
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/20' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredInsertSetGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                assignments: Generators::tuple(
                    new Program\Write\DeferredColumnAssignmentGenerator(
                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        ),
                        value: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: Generators::tuple(
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('unknown'),
                                        quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
                                    ),
                                    scope: Generators::constant(null),
                                    instance: Generators::constant(null)
                                )]),
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )])
                    ),
                    new \Tests\Generator\MySql\Statement\Dml\AssignmentGenerator(
                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('b'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        ),
                        value: new \Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator()
                    )
                ),
                alias: Generators::constant(null),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/21' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator::operands(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Name\TableWildcardGenerator(
                            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('q'),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            )
                        )])
                    ),
                    from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('missing'),
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
                )]),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/22' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredInsertRowsGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('d'),
                                quote: Generators::constant(null)
                            ),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                rows: Generators::tuple(
                    new Program\Write\DeferredInsertedRowGenerator(
                        values: Generators::tuple(
                            new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\DeferredWeightStringGenerator(
                                subject: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator(
                                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                        value: Generators::constant('other'),
                                                        quote: Generators::constant(null)
                                                    ),
                                                    schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                        value: Generators::constant('d'),
                                                        quote: Generators::constant(null)
                                                    ),
                                                    catalog: Generators::constant(null)
                                                )
                                            )])
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )]),
                                cast: Generators::constant(null),
                                length: Generators::constant(null),
                                levels: Generators::constant([]),
                                range: Generators::constant(null)
                            )])
                        )
                    )
                ),
                alias: Generators::constant(null),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/23' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::operands(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('t'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                commands: Generators::tuple(
                    new Program\Write\DeferredDefaultSettingGenerator(
                        column: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        ),
                        expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: Generators::tuple(
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )]),
                        columnWritten: Generators::constant(false)
                    ),
                    new \Tests\Generator\MySql\Statement\Alter\Command\ForceGenerator()
                ),
                ignore: Generators::constant(false)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/24' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredMultipleDeleteGenerator(
                with: Generators::constant(null),
                options: Generators::constant([]),
                input: new \Tests\Generator\MySql\Statement\Dml\Target\DeletionInputGenerator(
                    tables: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\DerivedTableGenerator(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\StarGenerator()])
                                ),
                                from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('source'),
                                            quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
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
                            )]),
                            alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('q'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            lateral: Generators::constant(false),
                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::Bare])
                        )])
                    ),
                    targets: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('q'),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        )
                    ),
                    resolutions: Generators::constant(null)
                ),
                form: \Tests\Generator\MySql\Statement\Dml\MultipleDeleteFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::BeforeFrom]),
                where: Generators::constant(null),
                hints: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/25' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        mode: ParameterModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode::Out])
                    )
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\VariableDeclarationGenerator(
                        names: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('b'),
                                quote: Generators::constant(null)
                            )
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        default: Generators::constant(null)
                    )]),
                    new Program\DeclarationGenerator([new Program\Cursor\CursorDeclarationGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        ),
                        query: \Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )]),
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
                        )
                    )])
                ),
                statements: Generators::tuple(
                    new Program\ProgramStatementGenerator([new Program\Cursor\FetchCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        ),
                        targets: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('B'),
                                quote: Generators::constant(null)
                            )
                        )
                    )])
                ),
                label: Generators::constant(null),
                endLabel: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/26' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        mode: Generators::constant(null)
                    )
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredAssignmentsGenerator(
                items: Generators::tuple(
                    new Program\DeferredAssignmentGenerator(
                        target: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        ),
                        value: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: Generators::tuple(
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        scope: Generators::constant(null),
                        qualifier: Generators::constant(null)
                    )
                )
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/27' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator::operands(
                privileges: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\AllPrivilegesGenerator()])
                ),
                kind: \Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure]),
                level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Level\GlobalLevelGenerator()]),
                grantees: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                        user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                            user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('u'),
                                quote: Generators::constant(null)
                            ),
                            host: Generators::constant(null)
                        )]),
                        identification: Generators::constant(null)
                    )
                ),
                tls: Generators::constant(null),
                options: Generators::constant([]),
                as: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/28' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator::operands(
                privileges: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\AllPrivilegesGenerator()])
                ),
                kind: \Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure]),
                level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Level\GlobalLevelGenerator()]),
                grantees: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                        user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                            user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('u'),
                                quote: Generators::constant(null)
                            ),
                            host: Generators::constant(null)
                        )]),
                        identification: Generators::constant(null)
                    )
                ),
                tls: Generators::constant(null),
                options: Generators::constant([]),
                as: new \Tests\Generator\MySql\Statement\Account\Privilege\GrantAsGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    roles: new \Tests\Generator\MySql\Statement\Account\User\RoleSelectionGenerator(
                        set: \Tests\Generator\MySql\Statement\Account\User\RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::All]),
                        roles: Generators::constant([])
                    )
                )
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/29' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredEvaluationGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Query\StarGenerator()
                )
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/30' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator::operands(
                privileges: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\AllPrivilegesGenerator()])
                ),
                kind: \Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
                level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Level\DatabaseLevelGenerator(
                    database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('d'),
                        quote: Generators::constant(null)
                    )
                )]),
                grantees: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                        user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                            user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('u'),
                                quote: Generators::constant(null)
                            ),
                            host: Generators::constant(null)
                        )]),
                        identification: Generators::constant(null)
                    )
                ),
                tls: Generators::constant(null),
                options: Generators::constant([]),
                as: new \Tests\Generator\MySql\Statement\Account\Privilege\GrantAsGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    roles: Generators::constant(null)
                )
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/31' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: \Tests\Generator\MySql\Statement\Server\Instance\KillScopeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::Query]),
                process: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                    elements: Generators::tuple(
                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('missing'),
                                quote: Generators::constant(null)
                            ),
                            scope: Generators::constant(null),
                            instance: Generators::constant(null)
                        )]),
                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    ),
                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/32' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredGroupStartGenerator(
                options: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Replication\Group\CredentialOptionGenerator(
                        credential: \Tests\Generator\MySql\Statement\Replication\Group\CredentialGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password]),
                        value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                            value: Generators::constant('p'),
                            radix: Generators::constant(null)
                        )
                    )
                )
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/33' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredPurgeGenerator(
                moment: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                    elements: Generators::tuple(
                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('missing'),
                                quote: Generators::constant(null)
                            ),
                            scope: Generators::constant(null),
                            instance: Generators::constant(null)
                        )]),
                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    ),
                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/34' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('local_value'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        mode: ParameterModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode::In])
                    )
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredExplainGenerator(
                statement: new \Tests\Generator\MySql\Statement\ExplainableGenerator([\Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('a'),
                                        quote: Generators::constant(null)
                                    ),
                                    qualifier: Generators::constant(null),
                                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                spellingProfile: Generators::constant(null)
                            )]),
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('local_value'),
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
                                    value: Generators::constant('t'),
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
                format: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('TRADITIONAL'),
                    quote: Generators::constant(null)
                ),
                into: Generators::constant(null),
                database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('shop'),
                    quote: Generators::constant(null)
                ),
                analyze: Generators::constant(true),
                modifier: Generators::constant(null),
                emptyInto: Generators::constant(false)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/35' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
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
                            value: Generators::constant('t'),
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
                options: Generators::constant([]),
                groupBy: new Program\Selection\DeferredGroupingGenerator(
                    items: Generators::tuple(
                        new Program\Selection\DeferredOrderItemGenerator(
                            expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('a'),
                                            quote: Generators::constant(null)
                                        ),
                                        qualifier: Generators::constant(null),
                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            direction: Generators::constant(null)
                        ),
                        new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            direction: Generators::constant(null)
                        )
                    ),
                    modifier: \Tests\Generator\MySql\Statement\Query\Clause\GroupingModifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::WithRollup])
                ),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/36' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredLikeGenerator(
                    operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )]),
                    pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    escape: Generators::constant(null),
                    negated: Generators::constant(false)
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/37' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Predicate\DeferredRegexpGenerator(
                    operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )]),
                    pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    negated: Generators::constant(false)
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/38' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        mode: Generators::constant(null)
                    )
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredSubqueryPredicateGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('x'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )]),
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
                            )]),
                            operator: Generators::constant(null),
                            quantifier: Generators::constant(null),
                            negated: Generators::constant(false)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/39' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredArithmeticGenerator(
                            operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus]),
                            left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            right: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                                items: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )
                                )
                            )])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/40' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredMembershipGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            elements: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )])
                            ),
                            negated: Generators::constant(false)
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                                items: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )
                                )
                            )])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/41' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredComparisonGenerator(
                    operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                    left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    right: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredGroupedGenerator(
                        operand: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredLikeGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            pattern: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )]),
                            escape: Generators::constant(null),
                            negated: Generators::constant(false)
                        )]),
                        escape: Generators::constant(null)
                    )])
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/42' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredTimeZoneGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            zone: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                value: Generators::constant('+00:00'),
                                radix: Generators::constant(null)
                            ),
                            interval: Generators::constant(true),
                            precision: Generators::constant('6')
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/43' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\Aggregate\DeferredJsonObjectAggregateGenerator(
                            key: new \Tests\Generator\Semantics\Statement\ScalarGenerator([\Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                                segments: Generators::tuple(
                                    Generators::constant('k')
                                ),
                                introducer: Generators::constant(null),
                                national: Generators::constant(false),
                                collation: Generators::constant(null)
                            )]),
                            value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum]),
                                arguments: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                distinct: Generators::constant(false),
                                all: Generators::constant(false),
                                over: Generators::constant(null)
                            )]),
                            keyAll: Generators::constant(false),
                            valueAll: Generators::constant(false),
                            over: Generators::constant(null)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/44' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\Aggregate\DeferredGroupConcatGenerator(
                            arguments: Generators::tuple(
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Rank]),
                                    arguments: Generators::constant([]),
                                    over: new \Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                                        base: Generators::constant(null),
                                        partition: Generators::constant([]),
                                        order: Generators::constant([]),
                                        frame: Generators::constant(null)
                                    )]),
                                    nulls: Generators::constant(null),
                                    edge: Generators::constant(null)
                                )])
                            ),
                            distinct: Generators::constant(false),
                            order: Generators::constant([]),
                            separator: Generators::constant(null),
                            over: Generators::constant(null)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/45' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\DeferredAggregateGenerator(
                            function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum]),
                            arguments: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            ),
                            distinct: Generators::constant(false),
                            all: Generators::constant(true),
                            over: Generators::constant(null)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/46' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredMemberOfGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    array: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )]),
                    of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/47' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredSubqueryGenerator(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(
                                rows: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(
                                        values: Generators::constant([])
                                    )
                                )
                            )]),
                            exists: Generators::constant(false)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/48' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredTruthTestGenerator(
                    operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )]),
                    truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                    negated: Generators::constant(false)
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/49' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: new Program\Expression\DeferredOperandGenerator([new Program\Expression\DeferredCallGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('ABS'),
                                quote: Generators::constant(null)
                            ),
                            arguments: Generators::tuple(
                                new Program\Expression\DeferredCallArgumentGenerator(
                                    expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                        elements: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )])
                                        ),
                                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )]),
                                    alias: Generators::constant(null),
                                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                                )
                            ),
                            schema: Generators::constant(null)
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                                items: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )
                                )
                            )])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/50' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Operator\DeferredIntervalGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            quantity: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedRowGenerator(
                                operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                escape: Generators::constant(null)
                            )]),
                            unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day]),
                            subtract: Generators::constant(false),
                            leading: Generators::constant(false)
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                                items: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )
                                )
                            )])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/51' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Server\DeferredKillGenerator(
                scope: Generators::constant(null),
                process: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Operator\DeferredAssignmentGenerator(
                    target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        )
                    ),
                    value: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )])
                )])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/52' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Operator\DeferredCollatedGenerator(
                            operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            collation: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('missing'),
                                quote: Generators::constant(null)
                            )
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/53' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Operator\DeferredLogicalGenerator(
                            operator: \Tests\Generator\MySql\Statement\Expression\LogicalOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::And]),
                            left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            right: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            symbolic: Generators::constant(false)
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                                items: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )
                                )
                            )])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/54' => [\SqlSemantics\Contract\GrammarRelease::MySql5744, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Write\DeferredInsertQueryGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator(
                    selection: new Program\DeferredSelectionGenerator(
                        items: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                                expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )]),
                                alias: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                            )])
                        ),
                        from: Generators::constant(null),
                        where: Generators::constant(null),
                        options: Generators::constant([]),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        windows: Generators::constant([]),
                        procedure: new \Tests\Generator\MySql\Statement\Query\Clause\ProcedureAnalyseGenerator(
                            arguments: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )
                            )
                        ),
                        qualify: Generators::constant(null),
                        into: Generators::constant(null)
                    )
                )]),
                onDuplicate: Generators::constant([])
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/55' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\DeferredWindowFunctionGenerator(
                            kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::FirstValue]),
                            arguments: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            ),
                            over: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('w'),
                                quote: Generators::constant(null)
                            ),
                            nulls: Generators::constant(null),
                            edge: Generators::constant(null)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('w'),
                            quote: Generators::constant(null)
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                            base: Generators::constant(null),
                            partition: Generators::constant([]),
                            order: Generators::tuple(
                                new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                                    expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::constant('1.5')
                                    )]),
                                    direction: Generators::constant(null)
                                )
                            ),
                            frame: Generators::constant(null)
                        )])
                    )
                ),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/56' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\VariableDeclarationGenerator(
                        names: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            )
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        default: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    )])
                ),
                statements: Generators::tuple(
                    new Program\ProgramStatementGenerator([new Program\Flow\LoopGenerator(
                        statements: Generators::tuple(
                            new Program\ProgramStatementGenerator([new Program\Flow\IfStatementGenerator(
                                branches: Generators::tuple(
                                    new Program\Flow\ConditionalBranchGenerator(
                                        condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                                            operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Greater]),
                                            left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::constant('x'),
                                                    quote: Generators::constant(null)
                                                ),
                                                qualifier: Generators::constant(null),
                                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )]),
                                            right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )])
                                        )]),
                                        statements: Generators::tuple(
                                            new Program\ProgramStatementGenerator([new Program\Flow\LeaveGenerator(
                                                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::constant('l'),
                                                    quote: Generators::constant(null)
                                                )
                                            )])
                                        )
                                    )
                                ),
                                otherwise: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator::operands(
                                        items: Generators::tuple(
                                            new \Tests\Generator\MySql\Statement\Utility\Set\SetItemGenerator([new \Tests\Generator\MySql\Statement\Utility\Set\NameAssignmentGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::constant('x'),
                                                    quote: Generators::constant(null)
                                                ),
                                                value: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                                                    operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus]),
                                                    left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                            value: Generators::constant('x'),
                                                            quote: Generators::constant(null)
                                                        ),
                                                        qualifier: Generators::constant(null),
                                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                                    )]),
                                                    right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                    )])
                                                )]),
                                                scope: Generators::constant(null),
                                                qualifier: Generators::constant(null)
                                            )])
                                        )
                                    )]),
                                    new Program\ProgramStatementGenerator([new Program\Flow\IterateGenerator(
                                        label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('l'),
                                            quote: Generators::constant(null)
                                        )
                                    )])
                                )
                            )])
                        ),
                        label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('l'),
                            quote: Generators::constant(null)
                        ),
                        endLabel: Generators::constant(null)
                    )])
                ),
                label: Generators::constant(null),
                endLabel: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/61' => [\SqlSemantics\Contract\GrammarRelease::MySql5744, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::operands(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('t'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                commands: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Alter\Column\ChangeColumnGenerator(
                        column: Generators::constant(null),
                        definition: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('g'),
                                    quote: Generators::constant(null)
                                ),
                                table: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            ),
                            specification: new \Tests\Generator\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumnGenerator(
                                type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                                    width: Generators::constant(null),
                                    modifiers: Generators::constant([])
                                )]),
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )]),
                                collation: Generators::constant(null),
                                storage: Generators::constant(null),
                                attributes: Generators::constant([]),
                                references: Generators::constant(null)
                            )
                        ),
                        position: Generators::constant(null),
                        columnWritten: Generators::constant(false)
                    ),
                    new \Tests\Generator\MySql\Statement\Alter\Column\AddColumnGenerator(
                        column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                table: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            ),
                            specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                                type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                                    width: Generators::constant(null),
                                    modifiers: Generators::constant([])
                                )]),
                                attributes: Generators::constant([]),
                                references: Generators::constant(null)
                            )
                        ),
                        position: Generators::constant(null),
                        columnWritten: Generators::constant(false)
                    )
                ),
                ignore: Generators::constant(false)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/62' => [\SqlSemantics\Contract\GrammarRelease::MySql5744, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::operands(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('t'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                commands: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Alter\Column\ChangeColumnGenerator(
                        column: Generators::constant(null),
                        definition: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('g'),
                                    quote: Generators::constant(null)
                                ),
                                table: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            ),
                            specification: new \Tests\Generator\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumnGenerator(
                                type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                                    width: Generators::constant(null),
                                    modifiers: Generators::constant([])
                                )]),
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )]),
                                collation: Generators::constant(null),
                                storage: \Tests\Generator\MySql\Statement\Table\Column\Kind\GeneratedStorageGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage::Stored]),
                                attributes: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Table\Column\KeywordAttributeGenerator(
                                        keyword: \Tests\Generator\MySql\Statement\Table\Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::NotNull])
                                    )
                                ),
                                references: Generators::constant(null)
                            )
                        ),
                        position: Generators::constant(null),
                        columnWritten: Generators::constant(false)
                    ),
                    new \Tests\Generator\MySql\Statement\Alter\Column\AddColumnGenerator(
                        column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                table: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            ),
                            specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                                type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                                    width: Generators::constant(null),
                                    modifiers: Generators::constant([])
                                )]),
                                attributes: Generators::constant([]),
                                references: Generators::constant(null)
                            )
                        ),
                        position: Generators::constant(null),
                        columnWritten: Generators::constant(false)
                    )
                ),
                ignore: Generators::constant(false)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/74' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::operands(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('t'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                commands: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Alter\Command\SetTableOptionsGenerator(
                        options: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\Table\Option\EngineOptionGenerator(
                                engine: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('InnoDB'),
                                    quote: Generators::constant(null)
                                )
                            )
                        )
                    ),
                    new \Tests\Generator\MySql\Statement\Alter\Partition\PartitionByGenerator(
                        partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                            method: new \Tests\Generator\MySql\Statement\Partition\Method\DeferredMethodGenerator(
                                expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                    elements: Generators::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    ),
                                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                kind: Generators::constant(null),
                                linear: Generators::constant(true)
                            ),
                            partitions: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                text: Generators::constant('3'),
                                hexadecimal: Generators::constant(false)
                            ),
                            subpartitioning: Generators::constant(null),
                            definitions: Generators::constant([])
                        )
                    )
                ),
                ignore: Generators::constant(false)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/88' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\VariableDeclarationGenerator(
                        names: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            )
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        collation: Generators::constant(null),
                        default: Generators::constant(null)
                    )]),
                    new Program\DeclarationGenerator([new Program\Cursor\CursorDeclarationGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        ),
                        query: \Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
                        )
                    )])
                ),
                statements: Generators::tuple(
                    new Program\ProgramStatementGenerator([new Program\Cursor\OpenCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        )
                    )]),
                    new Program\ProgramStatementGenerator([new Program\Cursor\FetchCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        ),
                        targets: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            )
                        )
                    )]),
                    new Program\ProgramStatementGenerator([new Program\Cursor\FetchCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        ),
                        targets: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            )
                        )
                    )]),
                    new Program\ProgramStatementGenerator([new Program\Cursor\FetchCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        ),
                        targets: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            )
                        )
                    )]),
                    new Program\ProgramStatementGenerator([new Program\Cursor\CloseCursorGenerator(
                        cursor: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('k'),
                            quote: Generators::constant(null)
                        )
                    )])
                ),
                label: Generators::constant(null),
                endLabel: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/89' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Call\DeferredWindowAggregateGenerator(
                            aggregate: new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum]),
                                arguments: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('a'),
                                            quote: Generators::constant(null)
                                        ),
                                        qualifier: Generators::constant(null),
                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )])
                                ),
                                distinct: Generators::constant(false),
                                all: Generators::constant(false),
                                over: Generators::constant(null)
                            ),
                            over: new \Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                                base: Generators::constant(null),
                                partition: Generators::constant([]),
                                order: Generators::constant([]),
                                frame: new \Tests\Generator\MySql\Statement\Call\Window\FrameGenerator(
                                    unit: \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Groups]),
                                    start: new \Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow]),
                                        offset: Generators::constant(null),
                                        unit: Generators::constant(null)
                                    ),
                                    end: Generators::constant(null),
                                    exclusion: Generators::constant(null)
                                )
                            )])
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('t'),
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
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/92' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Conversion\DeferredCharsetConversionGenerator(
                            operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('utf8mb4'),
                                    quote: Generators::constant(null)
                                )
                            )
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
        yield 'CreateProcedure/additional/93' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateProcedureGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('p'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\DeferredProjectionGenerator(
                        expression: new Program\Expression\DeferredOperandGenerator([new Program\Expression\Branching\DeferredCaseGenerator(
                            operand: Generators::constant(null),
                            branches: Generators::tuple(
                                new Program\Expression\Branching\DeferredCaseBranchGenerator(
                                    condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    result: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                        elements: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )])
                                        ),
                                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )])
                                )
                            ),
                            else: Generators::constant(null)
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )]),
            characteristics: Generators::constant([]),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
    }

}
