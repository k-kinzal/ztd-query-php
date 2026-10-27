<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;

/**
 * Generates complete column indexes, including the reserved PRIMARY name.
 *
 * @implements ErisGenerator<CreateIndex>
 */
final class CreateIndexGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateIndex> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $operands = Generators::associative(['column' => new ColumnNameGenerator($release), 'invalid' => Generators::bool()]);
        $this->delegate = Generators::map(
            static function (array $data): CreateIndex {
                if (!$data['column'] instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnName || !is_bool($data['invalid'])) {
                    throw new LogicException('Index operands require a column name and a condition flag.');
                }
                return new CreateIndex(new Name($data['invalid'] ? 'PRIMARY' : 'idx'), new QualifiedName(new Name('target')), [new ColumnPart($data['column']->column)]);
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CreateIndex>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateIndex>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Name> $name The generated semantic operand
     * @param ErisGenerator<QualifiedName> $table The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart>> $parts The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind> $kind The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm|null> $algorithm The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption>> $options The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Alter\AlterOption>> $alterOptions The generated semantic operand
     * @return ErisGenerator<CreateIndex>
     */
    public static function fromOperands(ErisGenerator $name, ErisGenerator $table, ErisGenerator $parts, ErisGenerator $kind, ErisGenerator $algorithm, ErisGenerator $options, ErisGenerator $alterOptions): ErisGenerator
    {
        return Generators::map(static fn (array $parts): CreateIndex => new CreateIndex(name: $parts[0], table: $parts[1], parts: $parts[2], kind: $parts[3], algorithm: $parts[4], options: $parts[5], alterOptions: $parts[6]), \Tests\Generator\Operands::tuple($name, $table, $parts, $kind, $algorithm, $options, $alterOptions));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<CreateIndex>>
     */
    public static function forms(): iterable
    {
        yield 'CreateIndex/23' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText]),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/239' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText]),
            algorithm: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Key\Option\IndexParserGenerator(
                    parser: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            ),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/240' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText]),
            algorithm: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Key\Option\IndexVisibilityGenerator(
                    visible: Generators::constant(true)
                )
            ),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/241' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Key\Option\IndexUsingGenerator(
                    algorithm: Key\IndexAlgorithmGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm::Btree])
                )
            ),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/242' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Key\Option\KeyBlockSizeGenerator(
                    size: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )
            ),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/243' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Key\Option\IndexCommentGenerator(
                    comment: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )
            ),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/245' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                ),
                new Key\Deferred\ExpressionPartGenerator(
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
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            alterOptions: Generators::constant([])
        );
        yield 'CreateIndex/350' => CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                ),
                new Key\Deferred\ExpressionPartGenerator(
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
                    direction: \Tests\Generator\MySql\Statement\Query\DirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Direction::Ascending])
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            alterOptions: Generators::constant([])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{GrammarRelease, ErisGenerator<CreateIndex>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'CreateIndex/additional/66' => [GrammarRelease::MySql847, CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('i'),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ColumnPartGenerator(
                    column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('c'),
                        quote: Generators::constant(null)
                    ),
                    length: Generators::constant(null),
                    direction: Generators::constant(null)
                ),
                new Key\ExpressionPartGenerator(
                    expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Multiply]),
                        left: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('b'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
                        right: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    )]),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            alterOptions: Generators::constant([])
        )];
        yield 'CreateIndex/additional/70' => [GrammarRelease::MySql847, CreateIndexGenerator::fromOperands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('k'),
                quote: Generators::constant(null)
            ),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parts: \Tests\Generator\Operands::tuple(
                new Key\ArrayPartGenerator(
                    expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('j'),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    target: new \Tests\Generator\MySql\Statement\Type\CastTargetGenerator(
                        kind: \Tests\Generator\MySql\Statement\Type\Kind\CastKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed]),
                        length: Generators::constant(null),
                        scale: Generators::constant(null),
                        charset: Generators::constant(null),
                        words: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    direction: Generators::constant(null)
                )
            ),
            kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            alterOptions: Generators::constant([])
        )];
    }

}
