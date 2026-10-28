<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generates trigger headers, preserving explicit matching or distinct schemas.
 *
 * @implements ErisGenerator<CreateTrigger>
 */
final class CreateTriggerGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateTrigger> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct()
    {
        $operands = Generators::associative(['number' => Generators::choose(0, 10000), 'mismatch' => Generators::bool(), 'time' => Generators::elements(...M\Routine\Trigger\TriggerTime::cases()), 'event' => Generators::elements(...M\Routine\Trigger\TriggerEvent::cases())]);
        $this->delegate = Generators::map(
            static function (array $data): CreateTrigger {
                if (!is_int($data['number']) || !is_bool($data['mismatch']) || !$data['time'] instanceof M\Routine\Trigger\TriggerTime || !$data['event'] instanceof M\Routine\Trigger\TriggerEvent) {
                    throw new LogicException('Trigger operands require a name suffix, schema flag, time and event.');
                }
                return new CreateTrigger(new QualifiedName(new Name('tr' . $data['number']), new Name($data['mismatch'] ? 'other' : 'pbt')), $data['time'], $data['event'], new M\Routine\Trigger\TriggerTable(new QualifiedName(new Name('target'), new Name('pbt'))), new M\Routine\Program\Block([], []));
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CreateTrigger>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTrigger>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<QualifiedName> $name The generated semantic operand
     * @param ErisGenerator<M\Routine\Trigger\TriggerTime> $time The generated semantic operand
     * @param ErisGenerator<M\Routine\Trigger\TriggerEvent> $event The generated semantic operand
     * @param ErisGenerator<M\Routine\Trigger\TriggerTable> $table The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node> $body The generated semantic operand
     * @param ErisGenerator<M\Routine\Trigger\TriggerOrder|null> $order The generated semantic operand
     * @param ErisGenerator<M\Name\Account|null> $definer The generated semantic operand
     * @param ErisGenerator<bool> $ifNotExists The generated semantic operand
     * @param ErisGenerator<M\Routine\Definition\DefinitionSource|null>|null $definitionSource Generated input boundaries
     * @return ErisGenerator<CreateTrigger>
     */
    public static function fromOperands(ErisGenerator $name, ErisGenerator $time, ErisGenerator $event, ErisGenerator $table, ErisGenerator $body, ErisGenerator $order, ErisGenerator $definer, ErisGenerator $ifNotExists, ?ErisGenerator $definitionSource = null): ErisGenerator
    {
        return Generators::map(static fn (array $parts): CreateTrigger => new CreateTrigger(name: $parts[0], time: $parts[1], event: $parts[2], table: $parts[3], body: $parts[4], order: $parts[5], definer: $parts[6], ifNotExists: $parts[7], definitionSource: $parts[8]), \Tests\Generator\Operands::tuple($name, $time, $event, $table, $body, $order, $definer, $ifNotExists, $definitionSource ?? Generators::oneOf(Generators::constant(null), new Definition\DefinitionSourceGenerator())));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<CreateTrigger>>
     */
    public static function forms(): iterable
    {
        yield 'CreateTrigger/168' => CreateTriggerGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            time: Trigger\TriggerTimeGenerator::selecting([M\Routine\Trigger\TriggerTime::Before]),
            event: Trigger\TriggerEventGenerator::selecting([M\Routine\Trigger\TriggerEvent::Insert]),
            table: new Trigger\TriggerTableGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Dml\EvaluationGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                        expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([M\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        alias: Generators::constant(null),
                        layout: new \Tests\Generator\Semantics\Statement\Spelling\LayoutGenerator(
                            tokens: \Tests\Generator\Operands::tuple(
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
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([M\Name\AliasMark::As]),
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
            order: Generators::constant(null),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
        yield 'CreateTrigger/412' => CreateTriggerGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            time: Trigger\TriggerTimeGenerator::selecting([M\Routine\Trigger\TriggerTime::Before]),
            event: Trigger\TriggerEventGenerator::selecting([M\Routine\Trigger\TriggerEvent::Insert]),
            table: new Trigger\TriggerTableGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator()]),
            order: new Trigger\TriggerOrderGenerator(
                placement: Trigger\OrderPlacementGenerator::selecting([M\Routine\Trigger\OrderPlacement::Follows]),
                other: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            definer: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
            )]),
            ifNotExists: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<CreateTrigger>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'CreateTrigger/additional/17' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTriggerGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('tr'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            time: Trigger\TriggerTimeGenerator::selecting([M\Routine\Trigger\TriggerTime::Before]),
            event: Trigger\TriggerEventGenerator::selecting([M\Routine\Trigger\TriggerEvent::Insert]),
            table: new Trigger\TriggerTableGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('t'),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            body: new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Utility\Set\SetItemGenerator([new \Tests\Generator\MySql\Statement\Utility\Set\NameAssignmentGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        value: new \Tests\Generator\MySql\Statement\Utility\Set\BareNameGenerator(
                            word: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('b'),
                                quote: Generators::constant(null)
                            ),
                            qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('NEW'),
                                    quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            ),
                            system: Generators::constant(false),
                            parentheses: Generators::constant(0)
                        ),
                        scope: Generators::constant(null),
                        qualifier: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('NEW'),
                            quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
                        )
                    )])
                )
            )]),
            order: Generators::constant(null),
            definer: Generators::constant(null),
            ifNotExists: Generators::constant(false)
        )];
    }

}
