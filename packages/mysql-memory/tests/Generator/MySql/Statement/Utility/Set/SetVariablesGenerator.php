<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes SetVariables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>
 */
final class SetVariablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables> */
    use Composition;

    /**
     * Generates complete SetVariables values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
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

            $forms[] = self::operands(Generators::vector(1, new NamesAssignmentGenerator(\Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator::numbers())));

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables) {
                throw new LogicException('Publishing SetVariables must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>
     */
    public static function operands(ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables(items: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($items, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>>
     */
    public static function forms(): iterable
    {
        yield 'SetVariables/72' => SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new SetNamesGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: Generators::constant(null)
                    ),
                    collation: Generators::constant(null)
                )])
            )
        );
        yield 'SetVariables/233' => SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new SetCharacterSetGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        )
                    )
                )])
            )
        );
        yield 'SetVariables/381' => SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new NameAssignmentGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    value: SetWordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Default]),
                    scope: Generators::constant(null),
                    qualifier: Generators::constant(null)
                )])
            )
        );
        yield 'SetVariables/386' => SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new SystemAssignmentGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        scope: Generators::constant(null),
                        instance: Generators::constant(null)
                    ),
                    value: SetWordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Default])
                )])
            )
        );
        yield 'SetVariables/387' => SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new UserAssignmentGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('localhost'),
                            quote: Generators::constant(null)
                        )
                    ),
                    value: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
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
                    )])
                )])
            )
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'SetVariables/additional/86' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, SetVariablesGenerator::operands(
            items: Generators::tuple(
                new SetItemGenerator([new UserAssignmentGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        )
                    ),
                    value: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )])
                )]),
                new SetItemGenerator([new PasswordAssignmentGenerator(
                    password: new \Tests\Generator\MySql\Statement\Account\PasswordGenerator(
                        text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                            value: Generators::constant('x'),
                            radix: Generators::constant(null)
                        ),
                        function: \Tests\Generator\MySql\Statement\Account\PasswordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::Password])
                    ),
                    user: Generators::constant(null)
                )])
            )
        )];
    }

}
