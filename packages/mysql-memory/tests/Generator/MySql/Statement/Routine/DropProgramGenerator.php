<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropProgram values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\DropProgram>
 */
final class DropProgramGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\DropProgram> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<bool>|null $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $name, ?ErisGenerator $ifExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram(kind: $parts[0], name: $parts[1], ifExists: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $name,
            $ifExists ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\DropProgram>>
     */
    public static function forms(): iterable
    {
        yield 'DropProgram/32' => new DropProgramGenerator(
            kind: ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Event]),
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            ifExists: Generators::constant(false)
        );
    }

}
