<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedProgramBody values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody>
 */
final class RefusedProgramBodyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction|\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize|\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable> $problem Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList|null>|null $parameters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName|null>|null $returns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<list<int>>|null $typeEnds Generated constructor operand
     * @param ErisGenerator<int>|null $nameEnd Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $kind, ErisGenerator $problem, ?ErisGenerator $definer = null, ?ErisGenerator $parameters = null, ?ErisGenerator $returns = null, ?ErisGenerator $collation = null, ?ErisGenerator $typeEnds = null, ?ErisGenerator $nameEnd = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody(name: $parts[0], kind: $parts[1], problem: $parts[2], definer: $parts[3], parameters: $parts[4], returns: $parts[5], collation: $parts[6], typeEnds: $parts[7], nameEnd: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $kind,
            $problem,
            $definer ?? Generators::constant(null),
            $parameters ?? Generators::constant(null),
            $returns ?? Generators::constant(null),
            $collation ?? Generators::constant(null),
            $typeEnds ?? Generators::constant([]),
            $nameEnd ?? Generators::constant(0)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\RefusedProgramBody' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedProgramBodyGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'p' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            kind: \Tests\Generator\MySql\Statement\Routine\ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Procedure]),
            problem: new LanguageBodyMismatchGenerator(
                offset: Generators::constant(37)
            ),
            definer: Generators::constant(null),
            parameters: new \Tests\Generator\MySql\Statement\Routine\ParameterListGenerator(
                parameters: Generators::constant([])
            ),
            returns: Generators::constant(null),
            collation: Generators::constant(null),
            typeEnds: Generators::constant([]),
            nameEnd: Generators::constant(18)
        )];
    }

    /**
     * Keeps each body's condition inside its complete routine header.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody>}>
     */
    public static function nestedConditions(): iterable
    {
        $conditions = (static function (): iterable {
            yield from ParameterScopeMismatchGenerator::executionForms();
            yield from ScopedLocalAssignmentGenerator::executionForms();
            yield from LanguageBodyMismatchGenerator::executionForms();
        })();
        foreach ($conditions as $name => [$release, $problem]) {
            yield $name => [$release, new self(
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()),
                \Tests\Generator\MySql\Statement\Routine\ProgramKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Procedure]),
                $problem,
            )];
        }
    }

}
