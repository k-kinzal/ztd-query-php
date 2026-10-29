<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes AlterEvent values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent>
 */
final class AlterEventGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent> */
    use Composition;

    /**
     * Generates complete AlterEvent values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent>|null $operands A constrained form of this concrete result
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

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterEvent forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent) {
                throw new LogicException('Publishing AlterEvent must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule|null>|null $schedule Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion|null>|null $completion Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $newName Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus|null>|null $status Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node|null>|null $body Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource|null>|null $definitionSource Generated input boundaries
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $schedule = null, ?ErisGenerator $completion = null, ?ErisGenerator $newName = null, ?ErisGenerator $status = null, ?ErisGenerator $comment = null, ?ErisGenerator $body = null, ?ErisGenerator $definer = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $definitionSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent(name: $parts[0], schedule: $parts[1], completion: $parts[2], newName: $parts[3], status: $parts[4], comment: $parts[5], body: $parts[6], definer: $parts[7], statementInformation: $parts[8], inputSource: $parts[9], definitionSource: $parts[10]);
        }, Generators::tuple($name, $schedule ?? Generators::constant(null), $completion ?? Generators::constant(null), $newName ?? Generators::constant(null), $status ?? Generators::constant(null), $comment ?? Generators::constant(null), $body ?? Generators::constant(null), $definer ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $definitionSource ?? Generators::oneOf(Generators::constant(null), new Definition\DefinitionSourceGenerator())));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent>>
     */
    public static function forms(): iterable
    {
        yield 'AlterEvent/3' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Event\EventStatusGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus::Enable]),
            comment: Generators::constant(null),
            body: Generators::constant(null),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/146' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: new Event\ScheduleGenerator([new Event\RecurringScheduleGenerator(
                quantity: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            )])
                        )])
                    )])
                )]),
                unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour]),
                starts: Generators::constant(null),
                ends: Generators::constant(null)
            )]),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: Generators::constant(null),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/148' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::operands(
                items: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        alias: Generators::constant(null),
                        layout: new \Tests\Generator\Semantics\Statement\Spelling\LayoutGenerator(
                            tokens: Generators::tuple(
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(''),
                                    text: Generators::constant('(')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant('(')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant('(')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant('(')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant('USER')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant('(')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant(')')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant(')')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant(')')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant(')')
                                ),
                                new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                    gap: Generators::constant(' '),
                                    text: Generators::constant(')')
                                )
                            ),
                            trail: Generators::constant('')
                        ),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                            grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([\SqlSemantics\Contract\GrammarRelease::MySql847]),
                            lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                                settings: Generators::constant('')
                            ),
                            parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
                        )
                    )
                )
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/149' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\IfStatementGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/150' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\SearchedCaseGenerator(
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/151' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::constant([]),
                statements: Generators::constant([]),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/152' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\LoopGenerator(
                statements: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                ),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/161' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\ConditionDeclarationGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        value: new Condition\ErrorCodeGenerator(
                            code: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                text: Generators::constant('1'),
                                hexadecimal: Generators::constant(false)
                            )
                        )
                    )])
                ),
                statements: Generators::constant([]),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/162' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\VariableDeclarationGenerator(
                        names: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        collation: Generators::constant(null),
                        default: Generators::constant(null)
                    )])
                ),
                statements: Generators::constant([]),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/163' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\HandlerDeclarationGenerator(
                        action: Program\HandlerActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction::Exit]),
                        conditions: Generators::tuple(
                            new Condition\ConditionGenerator([new Condition\GeneralConditionGenerator(
                                class: Condition\ConditionClassGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass::SqlWarning])
                            )])
                        ),
                        statement: new \Tests\Generator\Semantics\Statement\NodeGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                    )])
                ),
                statements: Generators::constant([]),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/164' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\BlockGenerator(
                declarations: Generators::tuple(
                    new Program\DeclarationGenerator([new Program\Cursor\CursorDeclarationGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        query: \Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                )
                            )])
                        )
                    )])
                ),
                statements: Generators::constant([]),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/169' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\SimpleCaseGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            )])
                        )])
                    )])
                )]),
                branches: Generators::tuple(
                    new Program\Flow\ConditionalBranchGenerator(
                        condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        statements: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                        )
                    )
                ),
                otherwise: Generators::constant([])
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/170' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\WhileLoopGenerator(
                condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            )])
                        )])
                    )])
                )]),
                statements: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                ),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
        yield 'AlterEvent/171' => AlterEventGenerator::operands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            schedule: Generators::constant(null),
            completion: Generators::constant(null),
            newName: Generators::constant(null),
            status: Generators::constant(null),
            comment: Generators::constant(null),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new Program\Flow\RepeatLoopGenerator(
                condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                    arguments: Generators::constant([]),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                )])
                            )])
                        )])
                    )])
                )]),
                statements: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\StatementGenerator([\Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::operands()])
                ),
                label: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                endLabel: Generators::constant(null)
            )]),
            definer: Generators::constant(null)
        );
    }

}
