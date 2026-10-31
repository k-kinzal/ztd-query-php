<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use Tests\Generator\MySql\Statement\Call\Problem\NamedArgumentGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\ReservedFunctionGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\WrongArgumentCountGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Name\CollationNameGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\MisuseGenerator;
use Tests\Generator\MySql\Statement\Routine\ParameterListGenerator;
use Tests\Generator\MySql\Statement\Routine\ProgramKindGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\NonUniqueTableGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;
use Tests\Generator\MySql\Statement\Type\Problem\InvalidTypeSizeGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates RefusedProgramBody values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedProgramBody>
 */
final class RefusedProgramBodyGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedProgramBody> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedProgramBody from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedProgramBody => new RefusedProgramBody(name: $parts[0], kind: $parts[1], problem: $parts[2], definer: $parts[3], parameters: in_array($parts[1], [ProgramKind::Function, ProgramKind::Procedure], true) ? $parts[4] : null, returns: $parts[1] === ProgramKind::Function ? $parts[5] : null, collation: $parts[6], typeEnds: [], nameEnd: $parts[8]),
            Generators::suchThat(
                static fn (array $parts): bool => !($parts[2]) instanceof Misuse || ($parts[2])->rule === MisuseRule::RecursiveReferencePosition,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    new ProgramKindGenerator($release),
                    Generators::bind(Generators::choose(0, 10), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new MisuseGenerator($release),
                        1 => new ProgramProblemGenerator($release),
                        2 => new LanguageBodyMismatchGenerator($release),
                        3 => new ScopedLocalAssignmentGenerator($release),
                        4 => new ParameterScopeMismatchGenerator($release),
                        5 => new EmptyEventAlterationGenerator($release),
                        6 => new WrongArgumentCountGenerator($release),
                        7 => new NamedArgumentGenerator($release),
                        8 => new ReservedFunctionGenerator($release),
                        9 => new InvalidTypeSizeGenerator($release),
                        10 => new NonUniqueTableGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new ParameterListGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new DeclaredNameGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new CollationNameGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::choose(0, 100))),
                    Generators::choose(0, 100)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedProgramBody $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RefusedProgramBody>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedProgramBody>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
