<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateFunction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction>
 */
final class CreateFunctionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList> $parameters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> $returns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation> $implementation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource|null>|null $definitionSource Generated input boundaries
     */
    public function __construct(ErisGenerator $name, ErisGenerator $parameters, ErisGenerator $returns, ErisGenerator $implementation, ?ErisGenerator $collation = null, ?ErisGenerator $definer = null, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $definitionSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(name: $parts[0], parameters: $parts[1], returns: $parts[2], implementation: $parts[3], collation: $parts[4], definer: $parts[5], ifNotExists: $parts[6], statementInformation: $parts[7], inputSource: $parts[8], definitionSource: $parts[9]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $parameters,
            $returns,
            $implementation,
            $collation ?? Generators::constant(null),
            $definer ?? Generators::constant(null),
            $ifNotExists ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $definitionSource ?? Generators::oneOf(Generators::constant(null), new Definition\DefinitionSourceGenerator())
        ));
    }
    /**
     * Composes a complete binary return declaration with earlier notices and later body conditions.
     *
     * @return ErisGenerator<array{list<mixed>, \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, list<int>}>
     */
    public static function binaryReturns(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::bool(), Generators::choose(0, 255), Generators::choose(0, 3), Generators::bool()), static function (array $parts) use ($release): ErisGenerator {
            [$name, $variable, $length, $kind, $width] = $parts;
            $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character($variable ? \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::VarChar : \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, (string) $length, charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Binary));
            $body = match ($kind) {
                0 => new Program\Flow\ReturnStatementGenerator(new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::constant((string) $length))])),
                1 => Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block()),
                2 => Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block(statements: [new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::CalcFoundRows], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]))])),
                default => Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block(statements: [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor($name, [new \SqlSemantics\Statement\Identifier\Name('target')])])),
            };
            $parameters = new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name('argument'), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int, $width ? '5' : null))]);
            $definitions = new self(Generators::constant(new \SqlSemantics\Statement\Identifier\QualifiedName($name)), Generators::constant($parameters), Generators::constant($type), new Definition\FunctionImplementationGenerator($body), definitionSource: Generators::constant(null));
            $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            $warnings = [...array_fill(0, !$legacy && $width ? 1 : 0, 1681), ...($legacy ? [] : [1287]), 1235];
            return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction $definition) use ($release, $parts, $warnings): array {
                $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
                $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $definition);
                \SqlSemantics\Diagnostic\Check::invariant($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, 'A generated function keeps its declaration role.');
                return [$parts, $statement, $warnings];
            }, $definitions);
        });
    }

    /**
     * Generates binary TEXT returns, whose field domain permits a stored definition.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction>
     */
    public static function binaryTextReturns(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $types = Generators::elements([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::TinyText, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Text, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::MediumText, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::LongText, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Long, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::LongVarChar, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::LongCharVarying]);
        return Generators::map(static function (array $parts) use ($release): \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction {
            [$name, $kind, $value] = $parts;
            $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character($kind, charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Binary));
            $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['value_' . $value]));
            $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName($name), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $type, new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation($body, [new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism(true)]));
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
            $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $definition);
            \SqlSemantics\Diagnostic\Check::invariant($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, 'A generated function keeps its declaration role.');
            return $statement;
        }, \Tests\Generator\Operands::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), $types, Generators::choose(0, 1000)));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction>>
     */
    public static function forms(): iterable
    {
        yield 'CreateFunction/159' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                length: Generators::constant(null)
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/160' => new CreateFunctionGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            parameters: new ParameterListGenerator(
                parameters: \Tests\Generator\Operands::tuple(
                    new ParameterGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        collation: Generators::constant(null),
                        mode: Generators::constant(null)
                    )
                )
            ),
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                length: Generators::constant(null)
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/215' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                width: Generators::constant(null),
                modifiers: Generators::constant([])
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/216' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\FloatingGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\FloatingKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Real]),
                precision: Generators::constant(null),
                scale: Generators::constant(null),
                modifiers: Generators::constant([])
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/217' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\CharacterGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\CharacterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char]),
                length: Generators::constant('0'),
                national: Generators::constant(false),
                charset: Generators::constant(null)
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/218' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\BinaryGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\BinaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind::Binary]),
                length: Generators::constant('0')
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/219' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\TemporalGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\TemporalKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Year]),
                precision: Generators::constant(null),
                modifiers: Generators::constant([])
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/220' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\SpatialGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\SpatialKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind::Geometry])
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/221' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\EnumerationGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\EnumerationKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum]),
                members: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('0f'),
                        radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                    )
                ),
                charset: Generators::constant(null)
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/222' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\DecimalGenerator(
                precision: Generators::constant(null),
                scale: Generators::constant(null),
                modifiers: Generators::constant([])
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/225' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int]),
                width: Generators::constant(null),
                modifiers: \Tests\Generator\Operands::tuple(
                    \Tests\Generator\MySql\Statement\Type\Kind\NumericModifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Signed])
                )
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/234' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\CharacterGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\CharacterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char]),
                length: Generators::constant('0'),
                national: Generators::constant(false),
                charset: new \Tests\Generator\MySql\Statement\Type\CharsetAttributeGenerator(
                    form: \Tests\Generator\MySql\Statement\Type\Kind\CharsetFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Ascii]),
                    charset: Generators::constant(null),
                    mark: \Tests\Generator\MySql\Statement\Type\Kind\BinaryMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Absent])
                )
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new ExternalBodyGenerator(
                    code: new DollarQuotedTextGenerator(
                        text: Generators::constant('text'),
                        tag: Generators::constant('')
                    )
                ),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('javascript'),
                            quote: Generators::constant(null)
                        )
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateFunction/425' => new CreateFunctionGenerator(
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
            returns: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                length: Generators::constant(null)
            )]),
            implementation: new Definition\FunctionImplementationGenerator(
                body: new Program\Flow\FunctionBodyGenerator([new Program\Flow\ReturnStatementGenerator(
                    value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
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
                    )])
                )]),
                characteristics: \Tests\Generator\Operands::tuple(
                    new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                        external: Generators::constant(null)
                    )])
                )
            ),
            collation: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
    }

}
