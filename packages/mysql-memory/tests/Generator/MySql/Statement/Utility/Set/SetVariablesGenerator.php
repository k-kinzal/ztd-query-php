<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables(items: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>>
     */
    public static function forms(): iterable
    {
        yield 'SetVariables/72' => new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
                new SetItemGenerator([new SetNamesGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: Generators::constant(null)
                    ),
                    collation: Generators::constant(null)
                )])
            )
        );
        yield 'SetVariables/233' => new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
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
        yield 'SetVariables/381' => new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
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
        yield 'SetVariables/386' => new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
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
        yield 'SetVariables/387' => new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
                new SetItemGenerator([new UserAssignmentGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('localhost'),
                            quote: Generators::constant(null)
                        )
                    ),
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
        yield 'SetVariables/additional/86' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new SetVariablesGenerator(
            items: \Tests\Generator\Operands::tuple(
                new SetItemGenerator([new UserAssignmentGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        )
                    ),
                    value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
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
