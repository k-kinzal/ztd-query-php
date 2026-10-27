<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterRoutine values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine>
 */
final class AlterRoutineGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic>>|null $characteristics Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $name, ?ErisGenerator $characteristics = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine(kind: $parts[0], name: $parts[1], characteristics: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $name,
            $characteristics ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine>>
     */
    public static function forms(): iterable
    {
        yield 'AlterRoutine/4' => new AlterRoutineGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            characteristics: Generators::constant([])
        );
        yield 'AlterRoutine/153' => new AlterRoutineGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            characteristics: \Tests\Generator\Operands::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\RoutineLanguageGenerator(
                    external: Generators::constant(null)
                )])
            )
        );
        yield 'AlterRoutine/156' => new AlterRoutineGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            characteristics: \Tests\Generator\Operands::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\RoutineCommentGenerator(
                    text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'AlterRoutine/157' => new AlterRoutineGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            characteristics: \Tests\Generator\Operands::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\DataAccessGenerator(
                    level: Characteristic\AccessLevelGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel::NoSql])
                )])
            )
        );
        yield 'AlterRoutine/158' => new AlterRoutineGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            characteristics: \Tests\Generator\Operands::tuple(
                new Characteristic\CharacteristicGenerator([new Characteristic\SqlSecurityGenerator(
                    context: Characteristic\SecurityContextGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext::Definer])
                )])
            )
        );
    }

}
