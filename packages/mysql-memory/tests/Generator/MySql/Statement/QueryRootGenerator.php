<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as FieldType;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;
use Tests\Generator\Composition;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator as FieldTypeGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator;
use Tests\Generator\Semantics\Contract\LanguageProfileGenerator;
use Tests\Generator\Semantics\Statement\Fact\QueryFactGenerator;
use Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator;
use Tests\Generator\Semantics\Statement\Identifier\ComparisonGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Shape\FieldGenerator;
use Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator;
use Tests\Generator\Semantics\Statement\Source\SpanGenerator;
use Tests\Generator\Semantics\Statement\Type\KnownGenerator;
use Tests\Generator\Semantics\Statement\Type\NullabilityGenerator;
use Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator;
use Tests\Generator\Semantics\Statement\Type\TypeFactGenerator;

/**
 * Composes QueryRoot values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
 */
final class QueryRootGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\QueryRoot> */
    use Composition;

    /**
     * Generates complete QueryRoot values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<QueryRoot>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        $complete = $operands === null && $contexts === null && $release === GrammarRelease::MySql847;
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($release === GrammarRelease::MySql847) {
                $forms[] = self::jsonPath();
                $forms[] = self::invalidJsonPath();
                $forms[] = Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation $relation): QueryRoot => new QueryRoot(new Select([], [new SelectExpression(new StringLiteral(['value']))], $relation)), new Relation\EscapedRelationGenerator(new NameGenerator(), new Relation\TableReferenceGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new NameGenerator()))));
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No QueryRoot forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): QueryRoot {
            if (!$statement instanceof QueryRoot) {
                throw new LogicException('Publishing QueryRoot must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
        if ($complete) {
            $this->delegate = Generators::oneOf($this->delegate, Generators::oneOf(
                self::completeProjections(),
                self::missingColumn(),
                self::parameterProjection(),
                Generators::map(static fn (array $parts): QueryRoot => $parts[0], \Tests\Generator\Semantics\Statement\Shape\FieldLookupGenerator::queries()),
                \Tests\Generator\Semantics\Statement\Reference\Table\CommonTableGenerator::queries(),
                Generators::map(static fn (array $parts): QueryRoot => $parts[2], \Tests\Generator\Semantics\Statement\Reference\Column\ConditionalColumnGenerator::incompleteReads()),
                \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredDomainGenerator::parameterQueries(),
                \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredRoutineGenerator::queries(),
                Query\Problem\NameConversionGenerator::queries(),
                Expression\TupleGenerator::comparisons(),
                Hint\Comment\HintCommentGenerator::projections(),
                Generators::map(static fn (array $parts): QueryRoot => $parts[1], Variable\Catalog\SystemVariablesGenerator::readQueries()),
            ));
        }
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<QueryRoot>
     */
    public static function operands(ErisGenerator $query, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): QueryRoot {
            return new QueryRoot(query: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($query, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }

    /**
     * Generates complete numeric projections without database prerequisites.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function integerExpressions(GrammarRelease $release): ErisGenerator
    {
        $operands = Generators::bind(Generators::choose(1, 6), static function (int $width): ErisGenerator {
            return Generators::map(static function (array $values): QueryRoot {
                $items = [];
                foreach ($values as $position => [$expression]) {
                    $items[] = new SelectExpression($expression, new \SqlSemantics\Statement\Identifier\Name('value_' . $position));
                }
                return new QueryRoot(new Select([], $items));
            }, Generators::vector($width, \Tests\Generator\Semantics\Statement\ScalarGenerator::integers()));
        });
        return new self($release, $operands);
    }

    /**
     * Generates executable projections with their types, names and notices intact.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function completeProjections(): ErisGenerator
    {
        return Generators::oneOf(self::projection(), self::nullProjection(), self::warnedProjection(), self::spellingNotice(), self::trimmedAlias(), \Tests\Generator\Semantics\Statement\Type\ChoiceGenerator::realCasts(), Variable\UserVariableGenerator::missingState());
    }

    /**
     * Generates invocation-time query operands, preserving deferred expression roles.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function deferredSelections(): ErisGenerator
    {
        $forms = [];
        foreach (Routine\Program\DeferredSelectionGenerator::operandForms() as $selection) {
            $forms[] = Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection $body): QueryRoot => new QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($body)), $selection);
        }
        return new self(GrammarRelease::MySql847, Generators::oneOf(...$forms));
    }

    /**
     * Generates the session assignment required by a locally bound variable read.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables, QueryRoot}>
     */
    public static function assignedVariableReads(): ErisGenerator
    {
        return Variable\UserVariableGenerator::assignmentSequence();
    }

    /**
     * Generates reads with complete, incomplete or conflicting prior declarations.
     * Every sequence installs the definition and row needed at execution time.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, QueryRoot}>
     */
    public static function declaredReads(): ErisGenerator
    {
        return Generators::oneOf(
            self::declaredTableSequence(),
            \Tests\Generator\Semantics\Statement\Reference\Column\ConditionalColumnGenerator::incompleteReads(),
            \Tests\Generator\Semantics\Statement\Reference\Table\TableResolutionGenerator::reboundReads(),
        );
    }

    /**
     * Generates a complete scalar projection, including its local bindings.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name>|null $names The generated result name
     * @return ErisGenerator<QueryRoot>
     */
    public static function projection(GrammarRelease $release = GrammarRelease::MySql847, ?ErisGenerator $names = null): ErisGenerator
    {
        $collation = Collation::known('utf8mb4_unicode_ci');
        $types = new TypeFactGenerator([new KnownGenerator(new TypeDescriptorGenerator([new DomainGenerator(
            KindGenerator::selecting([Kind::String]),
            FieldTypeGenerator::selecting([FieldType::VarString]),
            length: Generators::constant(4),
            collation: Generators::constant($collation),
            coercibility: CoercibilityGenerator::selecting([Coercibility::Coercible]),
        )]))]);
        $nullable = NullabilityGenerator::selecting([Nullability::NotNull]);
        $expressions = StringLiteralGenerator::fromOperands(
            segments: Generators::vector(1, Generators::map(static fn (int $value): string => 'val' . $value, Generators::choose(0, 9))),
            introducer: Generators::constant(null),
            national: Generators::constant(false),
            collation: Generators::constant($collation),
            location: new SpanGenerator(),
            scalarInformation: new ScalarFactGenerator($types, $nullable),
        );
        $fields = new FieldGenerator(Generators::constant(0), new OutputSlotGenerator($names ?? new NameGenerator(), $types, $nullable), $expressions);
        $results = new QueryFactGenerator(Generators::vector(1, $fields), ComparisonGenerator::selecting([Comparison::AsciiInsensitive]));
        return Generators::map(static function (StatementSemantics $information): QueryRoot {
            $field = $information->output?->projection[0] ?? null;
            if (!$field instanceof Field || !$field->expression instanceof StringLiteral) {
                throw new LogicException('The generated projection contains one character expression.');
            }
            return new QueryRoot(new Select([], [new SelectExpression($field->expression, $field->name)], queryInformation: $information->output), $information);
        }, new StatementSemanticsGenerator(new LanguageProfileGenerator(Generators::constant($release)), output: $results, connection: Generators::constant($collation)));
    }
    /**
     * Generates a complete projection and its unquoted-name notice together.
     * The outer operands own all varying values, including the notice boundary,
     * so shrinking preserves the association with the generated alias.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function warnedProjection(): ErisGenerator
    {
        $names = new NameGenerator(Generators::elements(['full', 'FULL', 'Full']), Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Bare));
        $warnings = new \Tests\Generator\Semantics\Statement\Fact\WarningGenerator([
            new Notice\CodedWarningGenerator([
                new Notice\DeprecationGenerator(
                    Notice\DeprecatedGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull]),
                ),
            ]),
        ]);
        $notices = new \Tests\Generator\Semantics\Statement\Fact\NoticeGenerator($warnings, subject: $names, inputSource: new SpanGenerator());
        return Generators::map(static function (array $parts): QueryRoot {
            [$statement, $notice] = $parts;
            if (!$notice->subject instanceof \SqlSemantics\Statement\Identifier\Name) {
                throw new LogicException('The generated spelling notice belongs to an alias.');
            }
            $original = $statement->field(0);
            if (!$original->expression instanceof StringLiteral) {
                throw new LogicException('The generated projection contains one character expression.');
            }
            $slot = new \SqlSemantics\Statement\Shape\OutputSlot($notice->subject, $original->type, $original->nullability);
            $field = new Field(0, $slot, $original->expression);
            $output = new \SqlSemantics\Statement\Fact\QueryFact([$field], Comparison::AsciiInsensitive);
            $information = $statement->statementInformation();
            return new QueryRoot(
                new Select([], [new SelectExpression($original->expression, $notice->subject)], queryInformation: $output),
                new StatementSemantics($information->profile, $information->client, $output, warnings: [$notice], connection: $information->connection),
            );
        }, Generators::tuple(self::projection(names: Generators::constant(new \SqlSemantics\Statement\Identifier\Name('full', \SqlSemantics\Statement\Identifier\NameQuote::Bare))), $notices));
    }

    /**
     * Generates a missing-column resolution as part of the complete query meaning.
     * Its name, invalid type, field and diagnostic all describe the same occurrence.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function missingColumn(): ErisGenerator
    {
        $invalid = new \Tests\Generator\Semantics\Statement\Type\InvalidGenerator(
            new \Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator([
                new \Tests\Generator\Semantics\Statement\Reference\Column\MissingColumnGenerator(new NameGenerator()),
            ]),
        );
        return Generators::map(static function (array $parts): QueryRoot {
            [$type, $source] = $parts;
            $resolution = $type->cause;
            if (!$resolution instanceof \SqlSemantics\Statement\Reference\Column\MissingColumn) {
                throw new LogicException('The generated invalid field names an absent column.');
            }
            $expression = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(
                $resolution->name,
                scalarInformation: new \SqlSemantics\Statement\Fact\ScalarFact($type, Nullability::Dependent, $resolution),
                inputSource: $source,
            );
            $field = new Field(0, new \SqlSemantics\Statement\Shape\OutputSlot($resolution->name, $type, Nullability::Dependent), $expression, $resolution);
            $output = new \SqlSemantics\Statement\Fact\QueryFact([$field], Comparison::AsciiInsensitive);
            return new QueryRoot(
                new Select([], [new SelectExpression($expression)], queryInformation: $output),
                new StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(GrammarRelease::MySql847), output: $output, diagnostics: [$resolution]),
            );
        }, Generators::tuple($invalid, new SpanGenerator()));
    }

    /**
     * Generates JSON selection steps and the corresponding literal as one operand.
     * The call retains the generated Path object through binding and execution.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function jsonPath(): ErisGenerator
    {
        $indices = new Call\Json\Path\IndexGenerator(Generators::bool(), Generators::choose(0, 3));
        $legs = new Call\Json\Path\LegGenerator(
            Call\Json\Path\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind::Cell]),
            from: $indices,
        );
        $paths = new Call\Json\PathGenerator(Generators::vector(1, $legs));
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Call\Json\Path $path): QueryRoot {
            $index = $path->legs[0]->from;
            if ($index === null) {
                throw new LogicException('The generated cell step has its array index.');
            }
            $text = '$[' . ($index->last ? 'last-' : '') . $index->offset . ']';
            $call = new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(
                new StringLiteral(['[10,20,30,40]']),
                new StringLiteral([$text]),
                pathValue: $path,
            );
            return new QueryRoot(new Select([], [new SelectExpression($call, new \SqlSemantics\Statement\Identifier\Name('selected'))]));
        }, $paths);
    }

    /**
     * Generates a decoded-path failure and a literal with the same byte boundary.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function invalidJsonPath(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid $invalid): QueryRoot {
            $call = new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(
                new StringLiteral(['{}']),
                new StringLiteral([str_repeat(' ', $invalid->position)]),
                pathValue: $invalid,
            );
            return new QueryRoot(new Select([], [new SelectExpression($call, new \SqlSemantics\Statement\Identifier\Name('invalid'))]));
        }, new Call\Json\Path\InvalidGenerator(Generators::choose(0, 32)));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<QueryRoot>>
     */
    public static function forms(): iterable
    {
        yield 'QueryRoot/71' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            )])
        );
        yield 'QueryRoot/291' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                locking: Generators::tuple(
                    new Query\Locking\LockingClauseGenerator(
                        strength: Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::ShareMode]),
                        tables: Generators::constant([]),
                        action: Generators::constant(null),
                        wildcards: Generators::constant([])
                    )
                ),
                into: Generators::constant(null),
                intoPosition: Generators::constant(null)
            )])
        );
        yield 'QueryRoot/292' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                locking: Generators::constant([]),
                into: new Query\Into\IntoDumpfileGenerator(
                    file: new Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                ),
                intoPosition: Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterQuery])
            )])
        );
        yield 'QueryRoot/293' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ParenthesizedQueryGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                        table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        )
                    )]),
                    locking: Generators::constant([]),
                    into: new Query\Into\IntoDumpfileGenerator(
                        file: new Literal\TextGenerator(
                            value: Generators::constant('text'),
                            radix: Generators::constant(null)
                        )
                    ),
                    intoPosition: Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterQuery])
                )])
            )])
        );
        yield 'QueryRoot/294' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: NameGenerator::fromOperands(
                                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                )
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        );
        yield 'QueryRoot/295' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\SetOperationGenerator(
                left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                quantifier: Generators::constant(null),
                right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )])
            )])
        );
        yield 'QueryRoot/296' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ValuesQueryGenerator(
                rows: Generators::tuple(
                    new Query\ValueRowGenerator(
                        values: Generators::tuple(
                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NullLiteralGenerator()])
                        )
                    )
                )
            )])
        );
        yield 'QueryRoot/297' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\StarGenerator()])
                ),
                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: Generators::constant(null),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
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
                into: new Query\Into\IntoDumpfileGenerator(
                    file: new Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                ),
                intoPosition: Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems]),
                late: Generators::constant(null),
                hints: Generators::constant([])
            )])
        );
        yield 'QueryRoot/298' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                locking: Generators::tuple(
                    new Query\Locking\LockingClauseGenerator(
                        strength: Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::ShareMode]),
                        tables: Generators::constant([]),
                        action: Generators::constant(null),
                        wildcards: Generators::constant([])
                    ),
                    new Query\Locking\LockingClauseGenerator(
                        strength: Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update]),
                        tables: Generators::constant([]),
                        action: Query\Locking\LockedRowActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction::Nowait]),
                        wildcards: Generators::constant([])
                    )
                ),
                into: Generators::constant(null),
                intoPosition: Generators::constant(null)
            )])
        );
        yield 'QueryRoot/355' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                locking: Generators::constant([]),
                into: new Query\Into\IntoVariablesGenerator(
                    targets: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\OperandGenerator([new Query\Clause\ProgramVariableGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        )]),
                        new \Tests\Generator\Semantics\Statement\OperandGenerator([new Query\Clause\ProgramVariableGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        )])
                    )
                ),
                intoPosition: Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterQuery])
            )])
        );
        yield 'QueryRoot/356' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                locking: Generators::constant([]),
                into: new Query\Into\IntoOutfileGenerator(
                    file: new Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    ),
                    format: new Dml\Load\TextFileFormatGenerator(
                        charset: Generators::constant(null),
                        fields: Generators::constant([]),
                        lines: Generators::constant([])
                    )
                ),
                intoPosition: Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterQuery])
            )])
        );
        yield 'QueryRoot/408' => QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\SetOperationGenerator(
                left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                quantifier: Query\Set\SetQuantifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier::Distinct]),
                right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )])
            )])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{GrammarRelease, ErisGenerator<QueryRoot>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'QueryRoot/additional/0' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                hints: Generators::tuple(
                    new Hint\OptimizerHintGenerator([new Hint\Form\TableHintGenerator(
                        hint: Hint\HintNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Hint\HintName::NoMerge]),
                        block: Generators::constant(null),
                        tables: Generators::constant([])
                    )]),
                    new Hint\OptimizerHintGenerator([new Hint\Form\KeyHintGenerator(
                        hint: Hint\HintNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Hint\HintName::NoIcp]),
                        block: Generators::constant(null),
                        table: new Hint\Form\HintTableGenerator(
                            name: Generators::constant('t'),
                            block: Generators::constant(null)
                        ),
                        indexes: Generators::constant([])
                    )]),
                    new Hint\OptimizerHintGenerator([new Hint\Form\StrategyHintGenerator(
                        hint: Hint\HintNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Hint\HintName::NoSemijoin]),
                        block: Generators::constant(null),
                        strategies: Generators::constant([])
                    )]),
                    new Hint\OptimizerHintGenerator([new Hint\Form\VariableHintGenerator(
                        variable: Generators::constant('sql_mode'),
                        value: new Hint\Form\HintLiteralGenerator(
                            kind: Hint\Form\HintLiteralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Word]),
                            value: Generators::constant('ANSI')
                        )
                    )])
                )
            )])
        )];
        yield 'QueryRoot/additional/1' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::constant('t'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    indexHints: Generators::tuple(
                        new Relation\Hint\IndexHintGenerator(
                            action: Relation\Hint\IndexHintActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Use]),
                            scope: Generators::constant(null),
                            indexes: Generators::constant([])
                        ),
                        new Relation\Hint\IndexHintGenerator(
                            action: Relation\Hint\IndexHintActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Force]),
                            scope: Relation\Hint\IndexHintScopeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope::Join]),
                            indexes: Generators::tuple(
                                NameGenerator::fromOperands(
                                    value: Generators::constant('i'),
                                    quote: Generators::constant(null)
                                )
                            )
                        ),
                        new Relation\Hint\IndexHintGenerator(
                            action: Relation\Hint\IndexHintActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Ignore]),
                            scope: Relation\Hint\IndexHintScopeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope::OrderBy]),
                            indexes: Generators::tuple(
                                NameGenerator::fromOperands(
                                    value: Generators::constant('j'),
                                    quote: Generators::constant(null)
                                ),
                                NameGenerator::fromOperands(
                                    value: Generators::constant('k'),
                                    quote: Generators::constant(null)
                                )
                            )
                        )
                    ),
                    sample: Generators::constant(null),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
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
        )];
        yield 'QueryRoot/additional/2' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::constant('t'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: NameGenerator::fromOperands(
                        value: Generators::constant('x'),
                        quote: Generators::constant(null)
                    ),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: new Relation\Hint\TableSampleGenerator(
                        method: Relation\Hint\SamplingMethodGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod::System]),
                        percentage: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new Variable\UserVariableGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('p'),
                                quote: Generators::constant(null)
                            )
                        )])
                    ),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::Bare]),
                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
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
        )];
        yield 'QueryRoot/additional/3' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\OdbcJoinGenerator(
                    relation: new Relation\TableSourceGenerator([new Relation\JoinedTableGenerator(
                        left: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: NameGenerator::fromOperands(
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
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        operator: Relation\Join\JoinOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Left]),
                        right: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: NameGenerator::fromOperands(
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
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        on: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Expression\ComparisonGenerator(
                            operator: Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                            left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                                name: NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: NameGenerator::fromOperands(
                                        value: Generators::constant('t'),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                ),
                                dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                                name: NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: NameGenerator::fromOperands(
                                        value: Generators::constant('u'),
                                        quote: Generators::constant(null)
                                    ),
                                    schema: Generators::constant(null),
                                    catalog: Generators::constant(null)
                                ),
                                dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )]),
                        using: Generators::constant([])
                    )])
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
        )];
        yield 'QueryRoot/additional/4' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\OdbcJoinGenerator(
                    relation: new Relation\TableSourceGenerator([new Relation\NestedRelationGenerator(
                        relation: new Relation\TableSourceGenerator([new Relation\TableListGenerator(
                            members: Generators::tuple(
                                new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: NameGenerator::fromOperands(
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
                                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: NameGenerator::fromOperands(
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
                                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )])
                            )
                        )])
                    )])
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
        )];
        yield 'QueryRoot/additional/5' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: NameGenerator::fromOperands(
                                    value: Generators::constant('d'),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            ),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\DerivedTableGenerator(
                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                                    name: NameGenerator::fromOperands(
                                        value: Generators::constant('a'),
                                        quote: Generators::constant(null)
                                    ),
                                    qualifier: Generators::constant(null),
                                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                spellingProfile: Generators::constant(null)
                            )])
                        ),
                        from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: NameGenerator::fromOperands(
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
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
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
                    alias: NameGenerator::fromOperands(
                        value: Generators::constant('d'),
                        quote: Generators::constant(null)
                    ),
                    columns: Generators::constant([]),
                    lateral: Generators::constant(false),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
        )];
        yield 'QueryRoot/additional/6' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\DerivedTableGenerator(
                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                        with: Generators::constant(null),
                        body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\SetOperationGenerator(
                            left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                            operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                            quantifier: Generators::constant(null),
                            right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                        orderBy: Generators::tuple(
                            new Query\OrderItemGenerator(
                                expression: Query\Clause\OutputOrdinalGenerator::forWidth(1),
                                direction: Generators::constant(null)
                            )
                        ),
                        limit: Generators::constant(null)
                    )]),
                    alias: NameGenerator::fromOperands(
                        value: Generators::constant('e'),
                        quote: Generators::constant(null)
                    ),
                    columns: Generators::constant([]),
                    lateral: Generators::constant(false),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
        )];
        yield 'QueryRoot/additional/7' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )]),
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('n'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\JsonTableGenerator(
                    document: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([StringLiteralGenerator::fromOperands(
                        segments: Generators::tuple(
                            Generators::constant('[1]')
                        ),
                        introducer: Generators::constant(null),
                        national: Generators::constant(false),
                        collation: Generators::constant(null)
                    )]),
                    path: StringLiteralGenerator::fromOperands(
                        segments: Generators::tuple(
                            Generators::constant('$[*]')
                        ),
                        introducer: Generators::constant(null),
                        national: Generators::constant(false),
                        collation: Generators::constant(null)
                    ),
                    columns: Generators::tuple(
                        new Call\JsonTableColumnGenerator([new Call\Json\OrdinalityColumnGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('n'),
                                quote: Generators::constant(null)
                            )
                        )]),
                        new Call\JsonTableColumnGenerator([new Call\Json\PathColumnGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            type: new Type\DeclaredNameGenerator([new Type\IntegralGenerator(
                                kind: Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                                width: Generators::constant(null),
                                modifiers: Generators::constant([])
                            )]),
                            path: StringLiteralGenerator::fromOperands(
                                segments: Generators::tuple(
                                    Generators::constant('$')
                                ),
                                introducer: Generators::constant(null),
                                national: Generators::constant(false),
                                collation: Generators::constant(null)
                            ),
                            exists: Generators::constant(false),
                            collation: Generators::constant(null),
                            onEmpty: Generators::constant(null),
                            onError: Generators::constant(null),
                            errorFirst: Generators::constant(false)
                        )])
                    ),
                    alias: NameGenerator::fromOperands(
                        value: Generators::constant('j'),
                        quote: Generators::constant(null)
                    ),
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
        )];
        yield 'QueryRoot/additional/8' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\DualGenerator(),
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
        )];
        yield 'QueryRoot/additional/9' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
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
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )]),
                where: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Expression\ComparisonGenerator(
                    operator: Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                    left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                        name: NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\TemporalLiteralGenerator(
                        form: Literal\TemporalFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp]),
                        text: Generators::constant('2024-01-02 03:04:05.25')
                    )])
                )]),
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
        )];
        yield 'QueryRoot/additional/78' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        spellingProfile: Generators::constant(null)
                    )])
                ),
                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: NameGenerator::fromOperands(
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
                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )]),
                where: Generators::constant(null),
                groupBy: new Query\Clause\GroupingGenerator(
                    items: Generators::tuple(
                        new Query\OrderItemGenerator(
                            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new Name\ColumnUseGenerator(
                                name: NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: Generators::constant(null),
                                dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            direction: Generators::constant(null)
                        )
                    ),
                    modifier: Generators::constant(null)
                ),
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
        )];
        yield 'QueryRoot/additional/79' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::tuple(
                    Query\SelectOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct])
                ),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                hints: Generators::tuple(
                    new Hint\OptimizerHintGenerator([new Hint\Form\ExecutionTimeHintGenerator(
                        milliseconds: Generators::constant('1000')
                    )])
                )
            )])
        )];
        yield 'QueryRoot/additional/80' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredValuesQueryGenerator(
                                rows: Generators::tuple(
                                    new Query\With\DeferredValueRowGenerator(
                                        values: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                elements: Generators::tuple(
                                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                    )]),
                                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                    )])
                                                ),
                                                keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )]),
                                            new Dml\DefaultRequestGenerator()
                                        )
                                    )
                                )
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/81' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\SetOperationGenerator(
                                left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\ExplicitTableGenerator(
                                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: NameGenerator::fromOperands(
                                            value: Generators::constant('t'),
                                            quote: Generators::constant(null)
                                        ),
                                        schema: Generators::constant(null),
                                        catalog: Generators::constant(null)
                                    )
                                )]),
                                operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Except]),
                                quantifier: Generators::constant(null),
                                right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredValuesQueryGenerator(
                                    rows: Generators::tuple(
                                        new Query\ValueRowGenerator(
                                            values: Generators::tuple(
                                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                )])
                                            )
                                        ),
                                        new Query\With\DeferredValueRowGenerator(
                                            values: Generators::tuple(
                                                new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Call\DeferredCharGenerator(
                                                    arguments: Generators::tuple(
                                                        new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\DeferredSubqueryGenerator(
                                                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                                                                options: Generators::constant([]),
                                                                items: Generators::tuple(
                                                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\StarGenerator()])
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
                                                            exists: Generators::constant(false)
                                                        )])
                                                    ),
                                                    charset: Generators::constant(null)
                                                )])
                                            )
                                        )
                                    )
                                )])
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/82' => [GrammarRelease::MySql5651, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\SetOperationGenerator(
                left: new Query\Set\LeadingUnionGenerator(
                    left: new Query\Set\LeadingUnionGenerator(
                        left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                            options: Generators::constant([]),
                            items: Generators::tuple(
                                new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                    expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    alias: Generators::constant(null),
                                    layout: Generators::constant(null),
                                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                        quantifier: Query\Set\SetQuantifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier::All]),
                        right: new Query\SelectGenerator(
                            options: Generators::constant([]),
                            items: Generators::tuple(
                                new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                    expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                                        name: NameGenerator::fromOperands(
                                            value: Generators::constant('a'),
                                            quote: Generators::constant(null)
                                        ),
                                        qualifier: Generators::constant(null),
                                        dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )]),
                                    alias: Generators::constant(null),
                                    layout: Generators::constant(null),
                                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                    spellingProfile: Generators::constant(null)
                                )])
                            ),
                            from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                    name: NameGenerator::fromOperands(
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
                                mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            where: Generators::constant(null),
                            groupBy: Generators::constant(null),
                            having: Generators::constant(null),
                            windows: Generators::constant([]),
                            qualify: Generators::constant(null),
                            orderBy: Generators::constant([]),
                            limit: Generators::constant(null),
                            procedure: Generators::constant(null),
                            locking: Generators::tuple(
                                new Query\Locking\LockingClauseGenerator(
                                    strength: Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update]),
                                    tables: Generators::constant([]),
                                    action: Generators::constant(null),
                                    wildcards: Generators::constant([])
                                )
                            ),
                            into: Generators::constant(null),
                            intoPosition: Generators::constant(null),
                            late: Generators::constant(null),
                            hints: Generators::constant([])
                        )
                    ),
                    quantifier: Generators::constant(null),
                    right: new Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                        limit: new Query\Clause\RowLimitGenerator(
                            count: new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            ),
                            offset: Generators::constant(null),
                            spelling: Generators::constant(null)
                        ),
                        procedure: Generators::constant(null),
                        locking: Generators::constant([]),
                        into: Generators::constant(null),
                        intoPosition: Generators::constant(null),
                        late: Generators::constant(null),
                        hints: Generators::constant([])
                    )
                ),
                operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                quantifier: Generators::constant(null),
                right: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
            )])
        )];
        yield 'QueryRoot/additional/83' => [GrammarRelease::MySql5651, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Expression\Subquery\ScalarSubqueryGenerator(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\Set\OrderedSetOperationGenerator(
                                left: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                                    options: Generators::constant([]),
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            alias: NameGenerator::fromOperands(
                                                value: Generators::constant('x'),
                                                quote: Generators::constant(null)
                                            ),
                                            layout: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                                operator: Query\Set\SetOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union]),
                                quantifier: Generators::constant(null),
                                right: new Query\SelectGenerator(
                                    options: Generators::constant([]),
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            alias: Generators::constant(null),
                                            layout: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                            spellingProfile: Generators::constant(null)
                                        )])
                                    ),
                                    from: new Relation\DualGenerator(),
                                    where: Generators::constant(null),
                                    groupBy: Generators::constant(null),
                                    having: Generators::constant(null),
                                    windows: Generators::constant([]),
                                    qualify: Generators::constant(null),
                                    orderBy: Generators::constant([]),
                                    limit: new Query\Clause\RowLimitGenerator(
                                        count: new Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        ),
                                        offset: Generators::constant(null),
                                        spelling: Generators::constant(null)
                                    ),
                                    procedure: Generators::constant(null),
                                    locking: Generators::constant([]),
                                    into: Generators::constant(null),
                                    intoPosition: Generators::constant(null),
                                    late: Generators::constant(null),
                                    hints: Generators::constant([])
                                ),
                                orderBy: Generators::tuple(
                                    new Query\OrderItemGenerator(
                                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new Name\ColumnUseGenerator(
                                            name: NameGenerator::fromOperands(
                                                value: Generators::constant('x'),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        )]),
                                        direction: Generators::constant(null)
                                    )
                                ),
                                limit: new Query\Clause\RowLimitGenerator(
                                    count: new Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    ),
                                    offset: Generators::constant(null),
                                    spelling: Generators::constant(null)
                                )
                            )])
                        )]),
                        alias: Generators::constant(null),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
        )];
        yield 'QueryRoot/additional/84' => [GrammarRelease::MySql5651, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Expression\Subquery\ScalarSubqueryGenerator(
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                                options: Generators::constant([]),
                                items: Generators::tuple(
                                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                                        expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Name\ColumnUseGenerator(
                                            name: NameGenerator::fromOperands(
                                                value: Generators::constant('a'),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        )]),
                                        alias: Generators::constant(null),
                                        layout: Generators::constant(null),
                                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        spellingProfile: Generators::constant(null)
                                    )])
                                ),
                                from: new Relation\TableSourceGenerator([new Relation\TableReferenceGenerator(
                                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: NameGenerator::fromOperands(
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
                                    mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                    dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                where: Generators::constant(null),
                                groupBy: Generators::constant(null),
                                having: Generators::constant(null),
                                windows: Generators::constant([]),
                                qualify: Generators::constant(null),
                                orderBy: Generators::constant([]),
                                limit: Generators::constant(null),
                                procedure: Generators::constant(null),
                                locking: Generators::tuple(
                                    new Query\Locking\LockingClauseGenerator(
                                        strength: Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update]),
                                        tables: Generators::constant([]),
                                        action: Generators::constant(null),
                                        wildcards: Generators::constant([])
                                    )
                                ),
                                into: Generators::constant(null),
                                intoPosition: Generators::constant(null),
                                late: new Query\Clause\LateOrderingGenerator(
                                    orderBy: Generators::tuple(
                                        new Query\OrderItemGenerator(
                                            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new Name\ColumnUseGenerator(
                                                name: NameGenerator::fromOperands(
                                                    value: Generators::constant('b'),
                                                    quote: Generators::constant(null)
                                                ),
                                                qualifier: Generators::constant(null),
                                                dot: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )]),
                                            direction: Generators::constant(null)
                                        )
                                    ),
                                    limit: new Query\Clause\RowLimitGenerator(
                                        count: new Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        ),
                                        offset: Generators::constant(null),
                                        spelling: Generators::constant(null)
                                    )
                                ),
                                hints: Generators::constant([])
                            )])
                        )]),
                        alias: NameGenerator::fromOperands(
                            value: Generators::constant('v'),
                            quote: Generators::constant(null)
                        ),
                        layout: Generators::constant(null),
                        mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
        )];
        yield 'QueryRoot/additional/90' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredBodyGenerator(
                                selection: new Routine\Program\DeferredSelectionGenerator(
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Routine\Program\DeferredProjectionGenerator(
                                            expression: new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Call\DeferredPositionGenerator(
                                                substring: new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                    elements: Generators::tuple(
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )]),
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )])
                                                    ),
                                                    keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )]),
                                                string: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                )])
                                            )]),
                                            alias: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/91' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredBodyGenerator(
                                selection: new Routine\Program\DeferredSelectionGenerator(
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Routine\Program\DeferredProjectionGenerator(
                                            expression: new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Constructor\DeferredRowGenerator(
                                                elements: Generators::tuple(
                                                    new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Predicate\DeferredRegexpGenerator(
                                                        operand: new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                            elements: Generators::tuple(
                                                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                                )]),
                                                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                                )])
                                                            ),
                                                            keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )]),
                                                        pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )]),
                                                        negated: Generators::constant(false)
                                                    )]),
                                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                    )])
                                                ),
                                                keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )]),
                                            alias: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/94' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredBodyGenerator(
                                selection: new Routine\Program\DeferredSelectionGenerator(
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Routine\Program\DeferredProjectionGenerator(
                                            expression: new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Conversion\DeferredBinaryCastGenerator(
                                                operand: new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                    elements: Generators::tuple(
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )]),
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )])
                                                    ),
                                                    keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )]),
                                            alias: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/95' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredBodyGenerator(
                                selection: new Routine\Program\DeferredSelectionGenerator(
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Routine\Program\DeferredProjectionGenerator(
                                            expression: new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Predicate\DeferredBetweenGenerator(
                                                operand: new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                    elements: Generators::tuple(
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )]),
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )])
                                                    ),
                                                    keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )]),
                                                lower: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                )]),
                                                upper: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                )]),
                                                negated: Generators::constant(false)
                                            )]),
                                            alias: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
        yield 'QueryRoot/additional/96' => [GrammarRelease::MySql847, QueryRootGenerator::operands(
            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\QueryExpressionGenerator(
                with: new Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: Generators::tuple(
                        new Query\With\CommonTableExpressionGenerator(
                            name: NameGenerator::fromOperands(
                                value: Generators::constant('x'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
                            query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\With\DeferredBodyGenerator(
                                selection: new Routine\Program\DeferredSelectionGenerator(
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Routine\Program\DeferredProjectionGenerator(
                                            expression: new Routine\Program\Expression\DeferredOperandGenerator([new Routine\Program\Expression\Predicate\DeferredSoundsLikeGenerator(
                                                operand: new Expression\RowOperandGenerator([new Expression\RowGenerator(
                                                    elements: Generators::tuple(
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )]),
                                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                        )])
                                                    ),
                                                    keyword: Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )]),
                                                pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                                )])
                                            )]),
                                            alias: Generators::constant(null),
                                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
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
                            )])
                        )
                    )
                ),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
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
                orderBy: Generators::constant([]),
                limit: Generators::constant(null)
            )])
        )];
    }


    /**
     * Generates CREATE, INSERT and SELECT with shared declarations and local references.
     * No semantic derivation or SQL text constructs any member of the sequence.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, QueryRoot}>
     */
    public static function declaredTableSequence(): ErisGenerator
    {
        $declarations = new \Tests\Generator\Semantics\Statement\Reference\Table\DeclaredTableGenerator(\Tests\Generator\Semantics\Statement\Declaration\TableGenerator::integers());
        $facts = \Tests\Generator\Semantics\Statement\Fact\RelationFactGenerator::declared(new \Tests\Generator\Semantics\Statement\Reference\Table\TableResolutionGenerator([$declarations]));
        $relations = new \Tests\Generator\Semantics\Statement\RelationGenerator([
            new \Tests\Generator\Semantics\Statement\NamedRelationGenerator([Relation\TableReferenceGenerator::bound($facts)]),
        ]);
        $resolutions = new \Tests\Generator\Semantics\Statement\Reference\Column\ResolutionGenerator([
            \Tests\Generator\Semantics\Statement\Reference\Column\ResolvedColumnGenerator::selecting($relations),
        ]);
        $numbers = new Literal\NumberLiteralGenerator(
            Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)),
            new ScalarFactGenerator(new TypeFactGenerator([new KnownGenerator(new TypeDescriptorGenerator([new DomainGenerator(KindGenerator::selecting([Kind::Integer]), FieldTypeGenerator::selecting([FieldType::Long]), length: Generators::constant(11))]))]), NullabilityGenerator::selecting([Nullability::NotNull])),
        );
        $rows = new Dml\Insert\InsertedRowGenerator(Generators::vector(2, $numbers));
        return Generators::map(static function (array $parts): array {
            [$resolution, $row, $use] = $parts;
            if (!$resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn || !$resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference) {
                throw new LogicException('The generated query selects a declared table column.');
            }
            $relation = $resolution->relation;
            $fact = $relation->relationInformation();
            if (!$fact->table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable || $resolution->slot->name === null) {
                throw new LogicException('The generated table and selected slot have names.');
            }
            $table = $fact->table->table;
            $information = new StatementSemantics($table->profile, connection: Collation::known('utf8mb4_unicode_ci'));
            $elements = array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition => new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName($column->name), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int))), $table->columns);
            foreach ($table->implicit as $hidden) {
                $elements[] = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName($hidden->column->name), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), [new \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute(\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Invisible)]));
            }
            foreach ($table->keys as $key) {
                $elements[] = new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition($key->primary ? \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary : \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Unique, array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart => new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart($column->name), $key->columns));
            }
            $create = new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($table->name, $elements, statementInformation: new StatementSemantics($table->profile, declarations: [$table], connection: $information->connection));
            $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($table->name, relationInformation: $fact);
            $insert = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, $target), [$row], statementInformation: $information);
            $expression = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($resolution->slot->name, qualifier: $use === \SqlSemantics\Contract\NameUse::Qualifier ? $table->name : null, scalarInformation: new \SqlSemantics\Statement\Fact\ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution));
            $field = new Field(0, $resolution->slot, $expression, $resolution);
            $output = new \SqlSemantics\Statement\Fact\QueryFact([$field], Comparison::AsciiInsensitive);
            $query = new QueryRoot(new Select([], [new SelectExpression($expression)], from: $relation, queryInformation: $output), new StatementSemantics($table->profile, output: $output, connection: $information->connection));
            return [$create, $insert, $query];
        }, Generators::tuple($resolutions, $rows, \Tests\Generator\Semantics\Contract\NameUseGenerator::selecting([\SqlSemantics\Contract\NameUse::Column, \SqlSemantics\Contract\NameUse::Qualifier])));
    }

    /**
     * Composes input spelling notices with the exact alias occurrence they describe.
     * The generated source map is consumed during construction and only local notices remain.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function spellingNotice(): ErisGenerator
    {
        $notices = new \Tests\Generator\Semantics\Statement\Source\SourceNoticeGenerator(
            new NameGenerator(Generators::elements(['full', 'FULL', 'Full']), Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Bare)),
            new \Tests\Generator\Semantics\Statement\Fact\WarningGenerator([new Notice\DeprecationGenerator(Notice\DeprecatedGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull]))]),
            Generators::choose(0, 128),
        );
        $sources = new \Tests\Generator\Semantics\Statement\Source\SourceMapGenerator(notices: Generators::vector(1, $notices));
        return Generators::map(static function (array $parts): QueryRoot {
            [$sources, $context] = $parts;
            $alias = $sources->notices[0]->subject;
            if (!$alias instanceof \SqlSemantics\Statement\Identifier\Name) {
                throw new LogicException('The spelling notice describes the generated projection alias.');
            }
            $input = new QueryRoot(new Select([], [new SelectExpression(new StringLiteral(['value']), $alias)]));
            $result = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $input, $sources);
            if (!$result instanceof QueryRoot) {
                throw new LogicException('Publishing the generated projection preserves its statement type.');
            }
            return $result;
        }, Generators::tuple($sources, \Tests\Generator\Semantics\Contract\AnalysisContextGenerator::mysql(GrammarRelease::MySql847)));
    }

    /**
     * Composes complete field collections into a tableless projection.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expressions Generated locally bound scalar occurrences
     * @return ErisGenerator<QueryRoot>
     */
    public static function namedScalar(ErisGenerator $expressions): ErisGenerator
    {
        $fields = new \Tests\Generator\Semantics\Statement\Shape\FieldsGenerator(Generators::vector(1, FieldGenerator::namedExpression($expressions)), ComparisonGenerator::selecting([Comparison::AsciiInsensitive]));
        return Generators::map(static function (\SqlSemantics\Statement\Shape\Fields $fields): QueryRoot {
            $field = $fields->at(0);
            if ($field->expression === null) {
                throw new LogicException('A generated scalar projection retains its expression.');
            }
            $output = new \SqlSemantics\Statement\Fact\QueryFact($fields->items, $fields->names);
            return new QueryRoot(new Select([], [new SelectExpression($field->expression, $field->name)], queryInformation: $output), new StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(GrammarRelease::MySql847), output: $output, connection: Collation::known('utf8mb4_unicode_ci')));
        }, $fields);
    }

    /**
     * Generates a NULL expression with its complete NULL-only type fact.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function nullProjection(): ErisGenerator
    {
        $types = new \Tests\Generator\Semantics\Statement\Type\NullOnlyGenerator();
        return self::namedScalar(new Literal\NullLiteralGenerator(new ScalarFactGenerator($types, NullabilityGenerator::selecting([Nullability::Nullable]))));
    }

    /**
     * Generates an unbound marker whose runtime input slot is already resolved.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function parameterProjection(): ErisGenerator
    {
        $missing = new \Tests\Generator\Semantics\Statement\Reference\Missing\MissingInputGenerator([
            new \Tests\Generator\Semantics\Statement\Reference\Missing\UnboundParameterGenerator(Generators::constant('?')),
        ]);
        $types = new \Tests\Generator\Semantics\Statement\Type\DependentGenerator(Generators::vector(1, $missing));
        $facts = new ScalarFactGenerator($types, NullabilityGenerator::selecting([Nullability::Dependent]), new \Tests\Generator\Semantics\Statement\Reference\Column\ResolutionGenerator([new Variable\ParameterBindingGenerator(Generators::constant(0))]));
        return self::namedScalar(new Literal\ParameterGenerator(scalarInformation: $facts));
    }

    /**
     * Generates a removed alias prefix, retaining the original name only in its warning.
     *
     * @return ErisGenerator<QueryRoot>
     */
    public static function trimmedAlias(): ErisGenerator
    {
        $warnings = new Notice\TrimmedNameGenerator(new NameGenerator(Generators::map(static fn (array $parts): string => str_repeat(' ', $parts[0]) . 'alias_' . $parts[1], Generators::tuple(Generators::choose(1, 8), Generators::choose(0, 100))), Generators::constant(null)));
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName $warning): QueryRoot {
            $alias = new \SqlSemantics\Statement\Identifier\Name(ltrim($warning->name->value));
            $input = new QueryRoot(new Select([], [new SelectExpression(new StringLiteral(['value']), $alias)]));
            $sources = new \SqlSemantics\Statement\Source\SourceMap(notices: [new \SqlSemantics\Statement\Source\SourceNotice($alias, $warning, 0)]);
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7'))->context();
            $result = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $input, $sources);
            \SqlSemantics\Diagnostic\Check::invariant($result instanceof QueryRoot, 'The generated alias belongs to a query.');
            return $result;
        }, $warnings);
    }

    /**
     * Composes complete read requests and their intrinsic or earlier dependency errors.
     * Constructor operands precede the bound graph so Eris can compare shrink inputs
     * without traversing diagnostic back references. An irreducible bind retains its value.
     *
     * @return ErisGenerator<array{list<mixed>, QueryRoot, int, int}>
     */
    public static function constrainedReads(GrammarRelease $release): ErisGenerator
    {
        return Generators::bind(Generators::tuple(Generators::choose(0, 4), new NameGenerator(), Generators::choose(0, 1000), Generators::bool(), Generators::bool()), static function (array $parts) use ($release): ErisGenerator {
            [$kind, $name, $value, $qualified, $missing] = $parts;
            $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $value), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
            $number = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $value);
            $table = new \SqlSemantics\Statement\Identifier\QualifiedName($name);
            $query = match ($kind) {
                0 => new Select([], [$qualified ? new \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard($table) : new \SqlSemantics\Platform\MySql\Statement\Query\Star()]),
                1 => new Select([], [new SelectExpression($number)], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) ($value + 2))))]),
                2 => new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($qualified ? new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('assigned')), $row) : $row)], $missing ? new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($table) : null)),
                3 => new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new SelectExpression($number)], new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), $row)),
                default => new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression($number)]), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))])),
            };
            $error = match ($kind) {
                0 => $qualified ? 1051 : 1096, 1 => 1054, 2 => $missing ? 1146 : 1241, 3 => 1241, default => 1222
            };
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value, searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $statement = \SqlSemantics\Facade\Semantics::fromStatement($semantics->context([], true), new QueryRoot($query));
            \SqlSemantics\Diagnostic\Check::invariant($statement instanceof QueryRoot, 'The generated read remains a concrete query statement.');
            return Generators::constant([$parts, $statement, $error, $kind === 2 && $qualified && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 1 : 0]);
        });
    }

    /**
     * Generates a read with the table and rows it depends on.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, QueryRoot}>
     */
    public static function withPrerequisites(GrammarRelease $release): ErisGenerator
    {
        $published = Generators::map(static fn (array $parts): array => self::readFrom($release, $parts[0], $parts[1]), Dml\Insert\InsertRowsGenerator::withPrerequisites($release));
        return $release === GrammarRelease::MySql847 ? Generators::oneOf($published, self::declaredReads()) : $published;
    }

    /**
     * Builds the read and its shared prerequisites from a generated declaration and insertion.
     *
     * @return array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, QueryRoot}
     * @throws LogicException When publication changes the concrete statement type.
     */
    public static function readFrom(GrammarRelease $release, \SqlSemantics\Platform\MySql\Statement\Table\CreateTable $table, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows $insert): array
    {
        $context = new \SqlSemantics\Contract\AnalysisContext($table->profile(), [new \SqlSemantics\Statement\Identifier\Name('pbt')], $table->declarations(), session: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($release));
        $statement = new QueryRoot(new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($table->name)));
        $query = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement);
        if (!$query instanceof QueryRoot) {
            throw new LogicException('Publishing a read preserves its concrete type.');
        }
        return [$table, $insert, $query];
    }
}
