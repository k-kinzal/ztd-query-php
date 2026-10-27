<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GetDiagnostics values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics>
 */
final class GetDiagnosticsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics> $information Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea|null>|null $area Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $information, ?ErisGenerator $area = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics(information: $parts[0], area: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $information,
            $area ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics>>
     */
    public static function forms(): iterable
    {
        yield 'GetDiagnostics/46' => new GetDiagnosticsGenerator(
            information: new StatementDiagnosticsGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new InformationItemGenerator(
                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('localhost'),
                                quote: Generators::constant(null)
                            )
                        ),
                        item: StatementItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName::Number])
                    )
                )
            ),
            area: Generators::constant(null)
        );
        yield 'GetDiagnostics/166' => new GetDiagnosticsGenerator(
            information: new ConditionDiagnosticsGenerator(
                number: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('localhost'),
                        quote: Generators::constant(null)
                    )
                ),
                items: \Tests\Generator\Operands::tuple(
                    new InformationItemGenerator(
                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('localhost'),
                                quote: Generators::constant(null)
                            )
                        ),
                        item: \Tests\Generator\MySql\Statement\Routine\Condition\ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::ClassOrigin])
                    )
                )
            ),
            area: Generators::constant(null)
        );
        yield 'GetDiagnostics/167' => new GetDiagnosticsGenerator(
            information: new StatementDiagnosticsGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new InformationItemGenerator(
                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('localhost'),
                                quote: Generators::constant(null)
                            )
                        ),
                        item: StatementItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName::Number])
                    )
                )
            ),
            area: DiagnosticsAreaGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea::Current])
        );
        yield 'GetDiagnostics/347' => new GetDiagnosticsGenerator(
            information: new ConditionDiagnosticsGenerator(
                number: new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    scope: Generators::constant(null),
                    instance: Generators::constant(null)
                ),
                items: \Tests\Generator\Operands::tuple(
                    new InformationItemGenerator(
                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('localhost'),
                                quote: Generators::constant(null)
                            )
                        ),
                        item: \Tests\Generator\MySql\Statement\Routine\Condition\ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::ClassOrigin])
                    )
                )
            ),
            area: Generators::constant(null)
        );
        yield 'GetDiagnostics/380' => new GetDiagnosticsGenerator(
            information: new ConditionDiagnosticsGenerator(
                number: new \Tests\Generator\MySql\Statement\LiteralExpressionGenerator([new \Tests\Generator\MySql\Statement\Literal\RadixLiteralGenerator(
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal]),
                    digits: Generators::constant('0f'),
                    introducer: Generators::constant(null)
                )]),
                items: \Tests\Generator\Operands::tuple(
                    new InformationItemGenerator(
                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('localhost'),
                                quote: Generators::constant(null)
                            )
                        ),
                        item: \Tests\Generator\MySql\Statement\Routine\Condition\ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::ClassOrigin])
                    )
                )
            ),
            area: Generators::constant(null)
        );
    }

}
